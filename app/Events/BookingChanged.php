<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Unit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int|null  $previousUnitId  set when the booking moved to another unit, so the previous
     *                                    unit's owner is told as well (their client then gets a 404 and drops the bar)
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly string $action,
        public readonly ?int $previousUnitId = null,
    ) {
        //
    }

    /**
     * Staff get everything on `calendar`; each Partner only hears about their own units on
     * `calendar.partner.{id}`. The payload carries no guest name, contact or price.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('calendar')];

        $partnerIds = Unit::query()
            ->whereIn('id', array_filter([$this->booking->unit_id, $this->previousUnitId]))
            ->whereNotNull('partner_id')
            ->pluck('partner_id')
            ->unique();

        foreach ($partnerIds as $partnerId) {
            $channels[] = new PrivateChannel("calendar.partner.{$partnerId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'booking.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'booking' => [
                'id' => $this->booking->id,
                'unit_id' => $this->booking->unit_id,
                'guest_id' => $this->booking->guest_id,
                'ota_source' => $this->booking->ota_source,
                'status' => $this->booking->status,
                'sync_status' => $this->booking->sync_status,
                'check_in' => $this->booking->check_in?->format('Y-m-d\TH:i:s'),
                'check_out' => $this->booking->check_out?->format('Y-m-d\TH:i:s'),
            ],
        ];
    }
}