<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $readerId;
    public int $senderId;

    /**
     * @param int $senderId The user who sent the messages being marked as read.
     * @param int $readerId The user who read the messages.
     */
    public function __construct(int $senderId, int $readerId)
    {
        $this->senderId = $senderId;
        $this->readerId = $readerId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.' . $this->senderId);
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }
}

