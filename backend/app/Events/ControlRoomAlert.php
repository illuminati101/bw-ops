<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ControlRoomAlert implements ShouldBroadcastNow
{
    public function __construct(public string $kind, public string $title, public string $body, public array $data = []) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('control-room')];
    }

    public function broadcastAs(): string
    {
        return 'alert';
    }

    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body] + $this->data;
    }
}
