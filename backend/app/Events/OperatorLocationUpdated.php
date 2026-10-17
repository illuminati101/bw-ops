<?php

namespace App\Events;

use App\Models\OperatorProfile;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class OperatorLocationUpdated implements ShouldBroadcastNow
{
    /** @param array<int, int> $bookingIds */
    public function __construct(public OperatorProfile $operator, public array $bookingIds = []) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('control-room')];
        foreach ($this->bookingIds as $id) {
            $channels[] = new PrivateChannel('booking.'.$id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'operator.location';
    }

    public function broadcastWith(): array
    {
        return [
            'operator_id' => $this->operator->id,
            'lat' => $this->operator->last_lat,
            'lng' => $this->operator->last_lng,
            'heading' => $this->operator->last_heading,
            'at' => $this->operator->last_location_at?->toIso8601String(),
            'online' => $this->operator->is_online,
        ];
    }
}
