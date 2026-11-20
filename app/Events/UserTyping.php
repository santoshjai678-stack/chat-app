<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $fromUserId;
    public int $toUserId;
    public bool $isTyping;

    public function __construct(int $fromUserId, int $toUserId, bool $isTyping)
    {
        $this->fromUserId = $fromUserId;
        $this->toUserId = $toUserId;
        $this->isTyping = $isTyping;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.' . $this->toUserId);
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }
}

