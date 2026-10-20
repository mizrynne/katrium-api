<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Guest;
use App\Models\Unit;
use App\Models\User;
use App\Support\ContactNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestService
{
    private const SYNCABLE_FIELDS = ['first_name', 'last_name', 'email', 'phone', 'mobile', 'country'];

    // ---------------------------------------------------------------------
    // Deduplication
    // ---------------------------------------------------------------------

    /**
     * Idempotent entry point for webhook ingest and booking creation.
     *
     * Match order: (1) email, case-insensitive; (2) phone/mobile in canonical form.
     * A match is only ever ENRICHED (blank fields filled), never overwritten, so
     * staff-curated data always beats OTA data. If email and phone resolve to two
     * different profiles, the email match wins and we never steal the other
     * profile's number. Concurrent ingests are settled by the DB unique indexes.
     */
    public function resolveOrCreateGuest(array $data): Guest
    {
        $attrs = Arr::only($this->normalizeForCreate($data), self::SYNCABLE_FIELDS);

        return DB::transaction(function () use ($attrs): Guest {
            if ($existing = $this->findMatch($attrs)) {
                return $this->enrich($existing, $attrs);
            }

            try {
                return DB::transaction(fn (): Guest => Guest::query()->create($attrs));
            } catch (UniqueConstraintViolationException $e) {
                $existing = $this->findMatch($attrs);

                if ($existing === null) {
                    throw $e;
                }

                return $this->enrich($existing, $attrs);
            }
        });
    }

    public function emailTaken(?string $email, ?int $ignoreGuestId = null): bool
    {
        $email = ContactNormalizer::email($email);

        return $email !== null
            && Guest::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->when($ignoreGuestId, fn (Builder $q, int $id) => $q->where('id', '!=', $id))
                ->exists();
    }

    public function phoneTaken(?string $phone, ?int $ignoreGuestId = null): bool
    {
        $phone = ContactNormalizer::phone($phone);

        return $phone !== null
            && Guest::query()
                ->where(fn (Builder $q) => $q->where('phone', $phone)->orWhere('mobile', $phone))
                ->when($ignoreGuestId, fn (Builder $q, int $id) => $q->where('id', '!=', $id))
                ->exists();
    }

    public function create(array $data): Guest
    {
        $attrs = $this->normalizeForCreate($data);

        try {
            return DB::transaction(fn (): Guest => Guest::query()->create($attrs));
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateContact($attrs);
        }
    }

    public function update(Guest $guest, array $data): Guest
    {
        $attrs = $this->normalizeFields($data);

        try {
            DB::transaction(function () use ($guest, $attrs): void {
                $guest->fill($attrs)->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateContact($attrs, $guest->getKey());
        }

        return $guest;
    }

    public function paginate(User $actor, array $filters = []): LengthAwarePaginator
    {
        $canManage = $actor->can('manage:guests');

        return $this->visibleTo(Guest::query(), $actor, $canManage)
            ->when($canManage, fn (Builder $q) => $this->withStats($q))
            ->when($canManage ? ($filters['tag'] ?? null) : null, fn (Builder $q, string $tag) => $q->where('tag', $tag))
            ->when($filters['search'] ?? null, fn (Builder $q, string $term) => $this->applySearch($q, $term, $canManage))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    public function findVisibleOrFail(User $actor, int $id): Guest
    {
        $canManage = $actor->can('manage:guests');
        $query = $this->visibleTo(Guest::query(), $actor, $canManage);

        if ($canManage) {
            $query = $this->withStats($query)->with([
                'violations' => fn ($q) => $q->latest('id')->with(['unit:id,name', 'recordedBy:id,name']),
            ]);
        }

        return $query->findOrFail($id);
    }

    private function normalizeFields(array $data): array
    {
        $out = [];

        foreach (['first_name', 'last_name', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_string($data[$field]) ? trim($data[$field]) : null;
                $out[$field] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('email', $data)) {
            $out['email'] = ContactNormalizer::email($data['email']);
        }

        foreach (['phone', 'mobile'] as $field) {
            if (array_key_exists($field, $data)) {
                $out[$field] = ContactNormalizer::phone($data[$field]);
            }
        }

        if (array_key_exists('country', $data)) {
            $country = is_string($data['country']) ? strtoupper(trim($data['country'])) : '';
            $out['country'] = $country === '' ? null : $country;
        }

        if (isset($data['tag'])) {
            $out['tag'] = $data['tag'];
        }

        return $out;
    }

    private function normalizeForCreate(array $data): array
    {
        $attrs = $this->normalizeFields($data);
        $attrs['phone'] = ($attrs['phone'] ?? null) ?? ($attrs['mobile'] ?? null);

        return $attrs;
    }

    private function findMatch(array $attrs): ?Guest
    {
        if (! empty($attrs['email'])) {
            $guest = Guest::query()
                ->whereRaw('LOWER(email) = ?', [$attrs['email']])
                ->lockForUpdate()
                ->first();

            if ($guest) {
                return $guest;
            }
        }

        $numbers = array_values(array_unique(array_filter([$attrs['phone'] ?? null, $attrs['mobile'] ?? null])));

        if ($numbers === []) {
            return null;
        }

        return Guest::query()
            ->where(fn (Builder $q) => $q->whereIn('phone', $numbers)->orWhereIn('mobile', $numbers))
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    private function enrich(Guest $guest, array $attrs): Guest
    {
        foreach (['first_name', 'last_name', 'country'] as $field) {
            if (blank($guest->{$field}) && filled($attrs[$field] ?? null)) {
                $guest->{$field} = $attrs[$field];
            }
        }

        if (blank($guest->email) && filled($attrs['email'] ?? null)
            && ! $this->emailTaken($attrs['email'], $guest->getKey())) {
            $guest->email = $attrs['email'];
        }

        foreach (['phone', 'mobile'] as $field) {
            if (blank($guest->{$field}) && filled($attrs[$field] ?? null)
                && ! $this->phoneTaken($attrs[$field], $guest->getKey())) {
                $guest->{$field} = $attrs[$field];
            }
        }

        if ($guest->isDirty()) {
            $guest->save();
        }

        return $guest;
    }

    private function duplicateContact(array $attrs, ?int $ignoreGuestId = null): ValidationException
    {
        $emailConflict = $this->emailTaken($attrs['email'] ?? null, $ignoreGuestId);

        return ValidationException::withMessages([
            $emailConflict ? 'email' : 'phone' => [
                $emailConflict
                    ? 'A guest profile with this email address already exists.'
                    : 'A guest profile with this phone number already exists.',
            ],
        ]);
    }

    private function visibleTo(Builder $query, User $actor, bool $canManage): Builder
    {
        if ($canManage) {
            return $query;
        }

        return $query->whereHas(
            'bookings',
            fn (Builder $b) => $b->whereIn(
                'unit_id',
                Unit::query()->where('partner_id', $actor->getKey())->select('id')
            )
        );
    }

    private function withStats(Builder $query): Builder
    {
        return $query
            ->withCount(['bookings', 'violations'])
            ->withSum(
                ['bookings as lifetime_value' => fn ($q) => $q->whereNotIn('status', ['canceled', 'noshow'])],
                'total_price'
            );
    }

    private function applySearch(Builder $query, string $term, bool $includeContact): Builder
    {
        $like = '%'.addcslashes(trim($term), '%_\\').'%';
        $digits = ltrim(preg_replace('/\D+/', '', $term) ?? '', '0');

        return $query->where(function (Builder $w) use ($like, $digits, $includeContact): void {
            $w->whereRaw("(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) ILIKE ?", [$like]);

            if ($includeContact) {
                $w->orWhere('email', 'ILIKE', $like)
                    ->orWhere('phone', 'ILIKE', $like)
                    ->orWhere('mobile', 'ILIKE', $like);

                if (strlen($digits) >= 4) {
                    $w->orWhere('phone', 'LIKE', "%{$digits}%")->orWhere('mobile', 'LIKE', "%{$digits}%");
                }
            }
        });
    }
}
