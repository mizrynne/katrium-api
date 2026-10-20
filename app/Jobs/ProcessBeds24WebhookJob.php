<?php

namespace App\Jobs;

use App\Exceptions\BookingOverlapException;
use App\Exceptions\BookingRuleException;
use App\Models\WebhookLog;
use App\Services\Beds24SyncService;
use App\Services\BookingService;
use App\Services\GuestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ProcessBeds24WebhookJob implements ShouldQueue
{
    use Queueable;

    public int $maxExceptions = 3;

    public int $timeout = 90;

    public function __construct(
        public readonly int $webhookLogId,
        public readonly string $beds24BookingId,
    ) {
        $this->onQueue(config('beds24.queues.webhooks'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("beds24-booking:{$this->beds24BookingId}"))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    public function backoff(): array
    {
        return [15, 60, 300];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function handle(Beds24SyncService $sync, GuestService $guests, BookingService $bookings): void
    {
        $log = WebhookLog::query()->find($this->webhookLogId);

        if (! $log || $log->status === 'processed') {
            return;
        }

        try {
            $normalized = $sync->parseInboundWebhook($log->payload);

            $result = DB::transaction(function () use ($normalized, $guests, $bookings) {
                $locked = WebhookLog::query()->whereKey($this->webhookLogId)->lockForUpdate()->firstOrFail();

                if ($locked->status === 'processed') {
                    return ['booking' => null, 'action' => 'duplicate'];
                }

                $guest = null;
                if ($normalized['intent'] === 'upsert' && $normalized['has_guest']) {
                    $guest = $guests->resolveOrCreateGuest($normalized['guest']);

                    if ($guest->tag === 'blacklisted') {
                        Log::warning('Beds24 booking received for a blacklisted guest.', [
                            'guest_id' => $guest->id,
                            'beds24_booking_id' => $normalized['beds24_booking_id'],
                        ]);
                    }
                }

                $result = $bookings->applyInbound($normalized, $guest);

                $locked->forceFill([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'error_message' => null,
                ])->save();

                return $result;
            });

            if (str_starts_with($result['action'], 'ignored') || $result['action'] === 'duplicate') {
                Log::info('Beds24 webhook acknowledged without changes.', [
                    'webhook_log_id' => $this->webhookLogId,
                    'action' => $result['action'],
                ]);
            }
        } catch (BookingRuleException|InvalidArgumentException $e) {
            $this->markFailed($e->getMessage());

            if ($e instanceof BookingOverlapException) {
                Log::critical('Beds24 inbound booking conflicts with an existing booking.', [
                    'webhook_log_id' => $this->webhookLogId,
                    'beds24_booking_id' => $this->beds24BookingId,
                    'message' => $e->getMessage(),
                    'conflict' => $e->conflict,
                ]);
            }
        }
    }

    public function failed(Throwable $e): void
    {
        $this->markFailed($e->getMessage());
    }

    private function markFailed(string $message): void
    {
        WebhookLog::query()
            ->whereKey($this->webhookLogId)
            ->where('status', '!=', 'processed')
            ->update([
                'status' => 'failed',
                'error_message' => Str::limit($message, 2000, '...'),
                'updated_at' => now(),
            ]);
    }
}