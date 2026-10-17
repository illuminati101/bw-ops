<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class BookingUpdated implements ShouldBroadcastNow
{
    public function __construct(public Booking $booking, public string $reason = 'updated') {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('booking.'.$this->booking->id), new PrivateChannel('control-room')];
        foreach ($this->booking->activeAssignments()->pluck('operator_profile_id') as $op) {
            $channels[] = new PrivateChannel('operator.'.$op);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'booking.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->booking->id,
            'reference' => $this->booking->reference,
            'status' => $this->booking->status->value,
            'status_label' => $this->booking->status->getLabel(),
            'reason' => $this->reason,
        ];
    }
}
