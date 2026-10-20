<?php

namespace App\Services;

use App\Events\BookingChanged;
use App\Exceptions\Beds24ApiException;
use App\Exceptions\Beds24RateLimitedException;
use App\Models\Booking;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Beds24SyncService
{
    private const TOKEN_CACHE_KEY = 'beds24:access_token';

    private const SENSITIVE_KEYS = [
        'title', 'firstName', 'lastName', 'email', 'phone', 'mobile', 'fax', 'company',
        'address', 'city', 'state', 'postcode', 'country', 'country2',
        'stripeToken', 'pcibookingToken',
    ];

    /**
     * @throws Beds24ApiException retryable=false for permanent failures, true for transient ones
     */
    public function pushDirectBooking(Booking $booking): Booking
    {
        $booking->loadMissing(['unit', 'guest']);

        if ($booking->ota_source !== 'direct') {
            return $booking;
        }

        $released = in_array($booking->status, config('beds24.non_blocking_statuses'), true);

        if ($released && blank($booking->beds24_booking_id)) {
            return $this->markSynced($booking, null);
        }

        if (blank($booking->unit?->beds24_room_id)) {
            throw Beds24ApiException::permanent("Unit #{$booking->unit_id} has no beds24_room_id; it cannot be synced.");
        }

        if (blank($booking->beds24_booking_id) && filled($booking->api_reference)) {
            if ($remoteId = $this->findRemoteIdByReference($booking->api_reference)) {
                $booking->beds24_booking_id = $remoteId;
            }
        }

        $isCreate = blank($booking->beds24_booking_id);
        $results = $this->request('POST', 'bookings', [$this->buildOutboundPayload($booking, $isCreate)]);
        $result = $results[0] ?? null;

        if (! is_array($result) || ! ($result['success'] ?? false)) {
            throw Beds24ApiException::permanent($this->describeFailure($result), $result['errors'] ?? []);
        }

        $remoteId = Arr::get($result, 'new.id') ?? Arr::get($result, 'modified.id') ?? $booking->beds24_booking_id;

        if ($isCreate && blank($remoteId)) {
            throw Beds24ApiException::permanent('Beds24 accepted the booking but returned no booking id. Verify manually before retrying.');
        }

        return $this->markSynced($booking, $remoteId ? (string) $remoteId : null);
    }

    public function markPushFailed(Booking $booking, string $message): void
    {
        $booking->forceFill([
            'sync_status' => 'failed',
            'sync_error' => Str::limit($message, 1000, '...'),
        ])->save();

        BookingChanged::dispatch($booking, 'synced');
    }

    public function idempotencyKey(array $payload): string
    {
        $b = $payload['booking'] ?? [];

        if (isset($b['id'], $b['modifiedTime'])) {
            return hash('sha256', implode('|', [
                'beds24', $b['id'], $b['modifiedTime'], $b['status'] ?? '', $b['arrival'] ?? '', $b['departure'] ?? '', $b['roomId'] ?? '',
            ]));
        }

        return hash('sha256', 'beds24|'.json_encode(Arr::except($payload, ['retries', 'timeStamp'])));
    }

    public function eventType(array $payload): string
    {
        return strtolower((string) Arr::get($payload, 'booking.status')) === 'cancelled'
            ? 'booking.cancelled'
            : 'booking.upsert';
    }

    /**
     * @throws InvalidArgumentException
     */
    public function parseInboundWebhook(array $payload): array
    {
        $b = $payload['booking'] ?? null;

        if (! is_array($b) || ! isset($b['id'], $b['roomId'], $b['arrival'], $b['departure'])) {
            throw new InvalidArgumentException('Beds24 webhook payload is missing booking.id / roomId / arrival / departure.');
        }

        $rawStatus = strtolower((string) ($b['status'] ?? 'confirmed'));
        $isBlock = $rawStatus === 'black';
        $status = config("beds24.inbound_status_map.{$rawStatus}", 'confirmed');

        if ($rawStatus === 'cancelled' && strcasecmp((string) ($b['subStatus'] ?? ''), 'noShow') === 0) {
            $status = 'noshow';
        }

        $channel = strtolower(trim((string) ($b['channel'] ?? '')));
        $channel = Arr::get(config('beds24.channel_aliases'), $channel, $channel);
        $otaSource = $isBlock ? 'block' : ($channel === '' ? 'direct' : $channel);

        $apiReference = filled($b['apiReference'] ?? null) ? (string) $b['apiReference'] : null;
        $prefix = preg_quote((string) config('beds24.api_reference_prefix'), '/');
        $localId = ($apiReference && preg_match("/^{$prefix}(\\d+)$/", $apiReference, $m)) ? (int) $m[1] : null;

        $guest = [
            'first_name' => $b['firstName'] ?? null,
            'last_name' => $b['lastName'] ?? null,
            'email' => $b['email'] ?? null,
            'phone' => $b['phone'] ?? null,
            'mobile' => $b['mobile'] ?? null,
            'country' => $b['country2'] ?? null,
        ];

        $modifiedAt = $b['modifiedTime'] ?? ($payload['timeStamp'] ?? null);

        return [
            'intent' => in_array($status, config('beds24.non_blocking_statuses'), true) ? 'cancel' : 'upsert',
            'beds24_booking_id' => (string) $b['id'],
            'room_id' => (string) $b['roomId'],
            'local_booking_id' => $localId,
            'api_reference' => $apiReference,
            'channel_confirmation_code' => ($apiReference && ! in_array($otaSource, ['direct', 'block'], true)) ? $apiReference : null,
            'ota_source' => $otaSource,
            'status' => $status,
            'status_code' => isset($b['statusCode']) ? (int) $b['statusCode'] : null,
            'arrival' => Carbon::parse($b['arrival'])->toDateString(),
            'departure' => Carbon::parse($b['departure'])->toDateString(),
            'num_adults' => max(1, (int) ($b['numAdult'] ?? 1)),
            'num_children' => max(0, (int) ($b['numChild'] ?? 0)),
            'total_price' => $isBlock ? 0.0 : (float) ($b['price'] ?? 0),
            'deposit' => $isBlock ? 0.0 : (float) ($b['deposit'] ?? 0),
            'commission' => $isBlock ? 0.0 : (float) ($b['commission'] ?? 0),
            'modified_at' => $modifiedAt,
            'has_guest' => ! $isBlock && count(array_filter($guest, fn ($v) => filled($v))) > 0,
            'guest' => $guest,
            'channel_data' => [
                'beds24_modified_time' => $modifiedAt,
                'webhook_timestamp' => $payload['timeStamp'] ?? null,
                'beds24' => Arr::except($b, self::SENSITIVE_KEYS),
            ],
        ];
    }

    private function buildOutboundPayload(Booking $booking, bool $isCreate): array
    {
        $guest = $booking->guest;

        $payload = [
            'id' => $isCreate ? null : (int) $booking->beds24_booking_id,
            'roomId' => (int) $booking->unit->beds24_room_id,
            'status' => config('beds24.outbound_status_map')[$booking->status] ?? 'confirmed',
            'arrival' => $booking->check_in->toDateString(),
            'departure' => $booking->check_out->toDateString(),
            'numAdult' => $booking->num_adults,
            'numChild' => $booking->num_children,
            'firstName' => $guest?->first_name,
            'lastName' => $guest?->last_name,
            'email' => $guest?->email,
            'mobile' => $guest?->mobile ?: $guest?->phone,
            'price' => (float) $booking->total_price,
            'deposit' => (float) $booking->deposit,
            'apiReference' => $booking->api_reference,
        ];

        $payload = array_filter($payload, fn ($v) => $v !== null && $v !== '');

        if ($isCreate) {
            $payload['actions'] = ['checkAvailability' => true, 'autoInvoiceItemCharge' => true];
        }

        return $payload;
    }

    private function findRemoteIdByReference(string $reference): ?string
    {
        $response = $this->request('GET', 'bookings', ['apiReference' => [$reference]]);
        $id = Arr::get($response, 'data.0.id');

        return $id ? (string) $id : null;
    }

    private function markSynced(Booking $booking, ?string $remoteId): Booking
    {
        $booking->forceFill([
            'beds24_booking_id' => $remoteId ?? $booking->beds24_booking_id,
            'sync_status' => 'synced',
            'sync_error' => null,
            'last_synced_at' => now(),
        ])->save();

        BookingChanged::dispatch($booking, 'synced');

        return $booking;
    }

    private function describeFailure(mixed $result): string
    {
        $messages = collect($result['errors'] ?? [])
            ->map(fn ($e) => trim(($e['field'] ?? '').' '.($e['message'] ?? '')))
            ->filter()
            ->implode('; ');

        return 'Beds24 rejected the booking'.($messages !== '' ? ": {$messages}" : '.');
    }

    /** @return array<mixed> */
    private function request(string $method, string $uri, array $data = [], bool $retriedAuth = false): array
    {
        try {
            $pending = Http::baseUrl(rtrim((string) config('beds24.base_url'), '/'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('beds24.http_timeout'))
                ->withHeaders(['token' => $this->accessToken()]);

            $response = match ($method) {
                'GET' => $pending->get($uri, $data),
                'POST' => $pending->post($uri, $data),
                default => throw new InvalidArgumentException("Unsupported method {$method}"),
            };
        } catch (ConnectionException $e) {
            throw new Beds24ApiException('Beds24 is unreachable: '.$e->getMessage());
        }

        if ($response->status() === 401 && ! $retriedAuth) {
            Cache::forget(self::TOKEN_CACHE_KEY);

            return $this->request($method, $uri, $data, true);
        }

        if ($response->status() === 429) {
            throw new Beds24RateLimitedException(((int) $response->header('X-FiveMinCreditLimit-ResetsIn') ?: 60) + 1);
        }

        if ($response->serverError()) {
            throw new Beds24ApiException("Beds24 server error ({$response->status()}).", true, $response->status());
        }

        if ($response->failed()) {
            throw Beds24ApiException::permanent(
                'Beds24 request failed: '.($response->json('error') ?? "HTTP {$response->status()}"),
                [],
                $response->status(),
            );
        }

        return $response->json() ?? [];
    }

    private function accessToken(): string
    {
        if ($cached = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $cached;
        }

        $refreshToken = config('beds24.refresh_token');
        if (blank($refreshToken)) {
            throw Beds24ApiException::permanent('BEDS24_REFRESH_TOKEN is not configured.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('beds24.base_url'), '/'))
                ->acceptJson()
                ->timeout((int) config('beds24.http_timeout'))
                ->withHeaders(['refreshToken' => $refreshToken])
                ->get('authentication/token');
        } catch (ConnectionException $e) {
            throw new Beds24ApiException('Beds24 auth is unreachable: '.$e->getMessage());
        }

        if ($response->serverError()) {
            throw new Beds24ApiException('Beds24 auth server error.', true, $response->status());
        }

        if ($response->failed() || blank($response->json('token'))) {
            throw Beds24ApiException::permanent('Beds24 rejected the refresh token. Generate a new invite code.', [], $response->status());
        }

        $ttl = max(60, (int) $response->json('expiresIn', 3600) - 120);
        Cache::put(self::TOKEN_CACHE_KEY, $response->json('token'), $ttl);

        return $response->json('token');
    }
}