<?php

namespace App\Jobs;

use App\Exceptions\Beds24ApiException;
use App\Exceptions\Beds24RateLimitedException;
use App\Models\Booking;
use App\Services\Beds24SyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class PushBookingToBeds24Job implements ShouldQueue
{
    use Queueable;

    public int $maxExceptions = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $bookingId)
    {
        $this->onQueue(config('beds24.queues.outbound'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("beds24-push:{$this->bookingId}"))->releaseAfter(10)->expireAfter(120),
        ];
    }

    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function handle(Beds24SyncService $sync): void
    {
        $booking = Booking::query()->find($this->bookingId);

        if (! $booking || $booking->ota_source !== 'direct') {
            return;
        }

        try {
            $sync->pushDirectBooking($booking);
        } catch (Beds24RateLimitedException $e) {
            $this->release($e->retryAfter);
        } catch (Beds24ApiException $e) {
            if ($e->retryable) {
                throw $e;
            }

            $sync->markPushFailed($booking, $e->getMessage());
        }
    }

    public function failed(Throwable $e): void
    {
        if ($booking = Booking::query()->find($this->bookingId)) {
            app(Beds24SyncService::class)->markPushFailed($booking, 'Beds24 sync gave up after retries: '.$e->getMessage());
        }
    }
}