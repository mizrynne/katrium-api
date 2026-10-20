<?php

namespace App\Events;

use App\Models\Booking;
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
     * Create a new event instance.
     */
    public function __construct(public readonly Booking $booking, public readonly string $action)
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('calendar')];
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
