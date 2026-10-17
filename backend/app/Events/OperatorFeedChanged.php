<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Tells a Crew app to refresh its offers / jobs. */
class OperatorFeedChanged implements ShouldBroadcastNow
{
    public function __construct(public int $operatorProfileId, public string $reason, public array $data = []) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('operator.'.$this->operatorProfileId)];
    }

    public function broadcastAs(): string
    {
        return 'feed.changed';
    }

    public function broadcastWith(): array
    {
        return ['reason' => $this->reason] + $this->data;
    }
}
