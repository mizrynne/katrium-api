<?php

namespace App\Services;

use App\Events\BookingChanged;
use App\Exceptions\BookingOverlapException;
use App\Exceptions\BookingRuleException;
use App\Jobs\PushBookingToBeds24Job;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    private const OTA_LOCKED_FIELDS = ['unit_id', 'check_in', 'check_out', 'status', 'total_price'];

    private const SYNC_FIELDS = [
        'unit_id', 'check_in', 'check_out', 'status', 'num_adults', 'num_children', 'total_price', 'deposit', 'guest_id',
    ];

    public function __construct(private readonly GuestService $guests) {}

    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $query = Booking::query()
            ->with([
                'unit:id,name,property_id',
                'guest:id,first_name,last_name,email,phone,mobile,tag,total_stays',
            ])
            ->visibleTo($user);

        $query
            ->when($filters['unit_id'] ?? null, fn (Builder $q, $v) => $q->where('unit_id', $v))
            ->when($filters['guest_id'] ?? null, fn (Builder $q, $v) => $q->where('guest_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->whereIn('status', Arr::wrap($v)))
            ->when($filters['ota_source'] ?? null, fn (Builder $q, $v) => $q->where('ota_source', $v))
            ->when($filters['sync_status'] ?? null, fn (Builder $q, $v) => $q->where('sync_status', $v))
            ->when($filters['property_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('unit', fn (Builder $u) => $u->where('property_id', $v)))
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->where('check_out', '>', Carbon::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->where('check_in', '<', Carbon::parse($v)->endOfDay()))
            ->when($filters['search'] ?? null, function (Builder $q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(function (Builder $w) use ($like) {
                    $w->where('channel_confirmation_code', 'ilike', $like)
                        ->orWhere('api_reference', 'ilike', $like)
                        ->orWhere('beds24_booking_id', 'ilike', $like)
                        ->orWhereHas('guest', fn (Builder $g) => $g
                            ->where('first_name', 'ilike', $like)
                            ->orWhere('last_name', 'ilike', $like)
                            ->orWhere('email', 'ilike', $like));
                });
            });

        $sort = $filters['sort'] ?? 'check_in';

        return $query
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 50))
            ->withQueryString();
    }

    public function isVisibleTo(Booking $booking, User $user): bool
    {
        return $user->can('manage:bookings')
            || $booking->unit()->where('partner_id', $user->id)->exists();
    }

    public function create(array $data): Booking
    {
        $unit = Unit::with('property')->findOrFail($data['unit_id']);
        $checkIn = $this->toStayTime($unit, $data['check_in'], 'in');
        $checkOut = $this->toStayTime($unit, $data['check_out'], 'out');
        $this->assertValidStay($checkIn, $checkOut);

        $otaSource = $data['ota_source'] ?? 'direct';

        $booking = DB::transaction(function () use ($data, $unit, $checkIn, $checkOut, $otaSource) {
            $guest = $this->resolveGuest($data);

            $booking = new Booking([
                'unit_id' => $unit->id,
                'guest_id' => $guest?->id,
                'ota_source' => $otaSource,
                'sync_status' => $otaSource === 'direct' ? 'pending' : 'not_applicable',
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'num_adults' => $data['num_adults'] ?? 1,
                'num_children' => $data['num_children'] ?? 0,
                'status' => $data['status'] ?? 'confirmed',
                'total_price' => $data['total_price'],
                'amount_paid' => $data['amount_paid'] ?? 0,
                'deposit' => $data['deposit'] ?? 0,
            ]);

            $this->persist($booking);

            if ($otaSource === 'direct') {
                $booking->forceFill(['api_reference' => config('beds24.api_reference_prefix').$booking->id])->save();
            }

            if ($guest) {
                $this->guests->refreshTotalStays($guest->id);
            }

            return $booking;
        });

        $this->dispatchPush($booking);
        BookingChanged::dispatch($booking, 'created');

        return $booking->load(['unit', 'guest']);
    }

    public function update(Booking $booking, array $data): Booking
    {
        $isDirect = $booking->ota_source === 'direct';

        if (! $isDirect) {
            $locked = array_values(array_intersect(self::OTA_LOCKED_FIELDS, array_keys($data)));
            if ($locked) {
                throw new BookingRuleException(
                    "Bookings imported from {$booking->ota_source} are managed by the channel and cannot be edited here: ".implode(', ', $locked).'.',
                    'ota_booking_locked',
                    collect($locked)->mapWithKeys(fn ($f) => [$f => ['This field is managed by the originating channel.']])->all(),
                );
            }
        }

        $booking->loadMissing('unit.property');
        $unit = (isset($data['unit_id']) && (int) $data['unit_id'] !== $booking->unit_id)
            ? Unit::with('property')->findOrFail($data['unit_id'])
            : $booking->unit;

        $attributes = Arr::only($data, ['num_adults', 'num_children', 'status', 'total_price', 'amount_paid', 'deposit']);

        if (isset($data['unit_id'])) {
            $attributes['unit_id'] = $unit->id;
        }
        if (isset($data['check_in'])) {
            $attributes['check_in'] = $this->toStayTime($unit, $data['check_in'], 'in');
        }
        if (isset($data['check_out'])) {
            $attributes['check_out'] = $this->toStayTime($unit, $data['check_out'], 'out');
        }

        $this->assertValidStay(
            $attributes['check_in'] ?? $booking->check_in,
            $attributes['check_out'] ?? $booking->check_out,
        );

        $oldGuestId = $booking->guest_id;

        DB::transaction(function () use ($booking, $attributes, $data, $isDirect, $oldGuestId) {
            if ($guest = $this->resolveGuest($data)) {
                $attributes['guest_id'] = $guest->id;
            }

            $booking->fill($attributes);

            if ($isDirect && $booking->isDirty(self::SYNC_FIELDS)) {
                $booking->sync_status = 'pending';
                $booking->sync_error = null;
            }

            $this->persist($booking);

            foreach (array_unique(array_filter([$oldGuestId, $booking->guest_id])) as $guestId) {
                $this->guests->refreshTotalStays($guestId);
            }
        });

        if ($booking->sync_status === 'pending') {
            $this->dispatchPush($booking);
        }
        BookingChanged::dispatch($booking, 'updated');

        return $booking->load(['unit', 'guest']);
    }

    public function cancel(Booking $booking): Booking
    {
        if ($booking->ota_source !== 'direct') {
            throw new BookingRuleException(
                "Bookings from {$booking->ota_source} must be cancelled on that channel; KatriuM will receive the cancellation automatically.",
                'ota_cancellation_not_allowed',
            );
        }

        if ($booking->status === 'canceled') {
            return $booking->load(['unit', 'guest']); // idempotent
        }

        DB::transaction(function () use ($booking) {
            $booking->fill(['status' => 'canceled', 'sync_status' => 'pending', 'sync_error' => null]);
            $this->persist($booking);

            if ($booking->guest_id) {
                $this->guests->refreshTotalStays($booking->guest_id);
            }
        });

        $this->dispatchPush($booking);
        BookingChanged::dispatch($booking, 'cancelled');

        return $booking->load(['unit', 'guest']);
    }

    /**
     * @param  array  $n  output of Beds24SyncService::parseInboundWebhook()
     * @return array{booking: ?Booking, action: string}
     */
    public function applyInbound(array $n, ?Guest $guest): array
    {
        $existing = Booking::query()
            ->where(function (Builder $q) use ($n) {
                $q->where('beds24_booking_id', $n['beds24_booking_id']);
                if ($n['local_booking_id']) {
                    $q->orWhere('id', $n['local_booking_id']);
                }
            })
            ->lockForUpdate()
            ->first();

        if ($existing && $this->isStale($existing, $n)) {
            return ['booking' => $existing, 'action' => 'ignored_stale'];
        }

        if ($n['intent'] === 'cancel') {
            if (! $existing) {
                return ['booking' => null, 'action' => 'ignored_unknown_cancellation'];
            }

            $existing->fill([
                'beds24_booking_id' => $n['beds24_booking_id'],
                'status' => $n['status'],
                'beds24_status' => $n['status_code'],
                'sync_status' => 'synced',
                'sync_error' => null,
                'last_synced_at' => now(),
                'channel_data' => $n['channel_data'],
            ]);
            $this->persist($existing);

            if ($existing->guest_id) {
                $this->guests->refreshTotalStays($existing->guest_id);
            }

            BookingChanged::dispatch($existing, 'cancelled');

            return ['booking' => $existing, 'action' => 'cancelled'];
        }

        $unit = Unit::with('property')->where('beds24_room_id', $n['room_id'])->first();
        if (! $unit) {
            throw new BookingRuleException(
                "No KatriuM unit is mapped to Beds24 roomId {$n['room_id']}.",
                'unit_not_mapped',
            );
        }

        $checkIn = $this->toStayTime($unit, $n['arrival'], 'in');
        $checkOut = $this->toStayTime($unit, $n['departure'], 'out');

        if ($existing && $existing->check_in->toDateString() === $n['arrival']) {
            $checkIn = $existing->check_in;
        }
        if ($existing && $existing->check_out->toDateString() === $n['departure']) {
            $checkOut = $existing->check_out;
        }

        $this->assertValidStay($checkIn, $checkOut);

        $attributes = [
            'unit_id' => $unit->id,
            'guest_id' => $guest?->id ?? $existing?->guest_id,
            'beds24_booking_id' => $n['beds24_booking_id'],
            'api_reference' => $n['api_reference'] ?? $existing?->api_reference,
            'channel_confirmation_code' => $n['channel_confirmation_code'] ?? $existing?->channel_confirmation_code,
            'ota_source' => $n['ota_source'],
            'sync_status' => 'synced',
            'sync_error' => null,
            'last_synced_at' => now(),
            'channel_data' => $n['channel_data'],
            'commission_amount' => $n['commission'],
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'num_adults' => $n['num_adults'],
            'num_children' => $n['num_children'],
            'status' => $n['status'],
            'beds24_status' => $n['status_code'],
            'total_price' => $n['total_price'],
            'deposit' => $n['deposit'],
        ];

        if ($existing) {
            $existing->fill($attributes);
            $this->persist($existing);
            $booking = $existing;
            $action = 'updated';
        } else {
            $booking = new Booking($attributes + ['amount_paid' => 0]);
            $this->persist($booking);
            $action = 'created';
        }

        if ($booking->guest_id) {
            $this->guests->refreshTotalStays($booking->guest_id);
        }

        BookingChanged::dispatch($booking, $action);

        return ['booking' => $booking, 'action' => $action];
    }

    private function persist(Booking $booking): void
    {
        try {
            DB::transaction(fn () => $booking->save());
        } catch (QueryException $e) {
            if (BookingOverlapException::causedBy($e)) {
                throw $this->overlapFor($booking);
            }

            throw $e;
        }
    }

    private function overlapFor(Booking $booking): BookingOverlapException
    {
        $conflict = Booking::query()
            ->where('unit_id', $booking->unit_id)
            ->whereNotIn('status', config('beds24.non_blocking_statuses'))
            ->when($booking->exists, fn (Builder $q) => $q->whereKeyNot($booking->getKey()))
            ->where('check_in', '<', $booking->check_out->copy()->addHour())
            ->where('check_out', '>', $booking->check_in->copy()->subHour())
            ->orderBy('check_in')
            ->first();

        if (! $conflict) {
            $message = 'The unit is not available for the requested dates.';

            return new BookingOverlapException($message, ['check_in' => [$message]]);
        }

        $message = sprintf(
            'The unit is already booked from %s to %s (booking #%d via %s). Choose different dates or another unit.',
            $conflict->check_in->format('Y-m-d H:i'),
            $conflict->check_out->format('Y-m-d H:i'),
            $conflict->id,
            $conflict->ota_source,
        );

        return new BookingOverlapException($message, ['check_in' => [$message], 'check_out' => [$message]], [
            'booking_id' => $conflict->id,
            'unit_id' => $conflict->unit_id,
            'check_in' => $conflict->check_in->format('Y-m-d\TH:i:s'),
            'check_out' => $conflict->check_out->format('Y-m-d\TH:i:s'),
            'ota_source' => $conflict->ota_source,
            'status' => $conflict->status,
        ]);
    }

    private function resolveGuest(array $data): ?Guest
    {
        if (! empty($data['guest_id'])) {
            return Guest::query()->findOrFail($data['guest_id']);
        }

        if (! empty($data['guest'])) {
            return $this->guests->resolveOrCreateGuest($data['guest']);
        }

        return null;
    }

    private function dispatchPush(Booking $booking): void
    {
        if ($booking->ota_source === 'direct') {
            PushBookingToBeds24Job::dispatch($booking->id);
        }
    }

    private function assertValidStay(\DateTimeInterface $checkIn, \DateTimeInterface $checkOut): void
    {
        if ($checkOut <= $checkIn) {
            $message = 'check_out must be after check_in.';

            throw new BookingRuleException($message, 'invalid_stay', ['check_out' => [$message]]);
        }
    }

    private function isStale(Booking $existing, array $n): bool
    {
        $previous = data_get($existing->channel_data, 'beds24_modified_time');

        return $previous && $n['modified_at']
            && Carbon::parse($n['modified_at'])->lt(Carbon::parse($previous));
    }

    private function toStayTime(Unit $unit, string $value, string $kind): Carbon
    {
        $tz = $unit->property?->timezone ?: config('app.timezone');
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $time = $kind === 'in'
                ? ($unit->property?->check_in_time ?: config('beds24.default_check_in_time'))
                : ($unit->property?->check_out_time ?: config('beds24.default_check_out_time'));

            return Carbon::parse("{$value} {$time}", $tz);
        }

        return Carbon::parse($value, $tz)->setTimezone($tz);
    }
}