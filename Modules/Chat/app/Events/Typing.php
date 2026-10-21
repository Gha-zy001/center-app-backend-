<?php

namespace Modules\Chat\app\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Typing implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $conversationId;
    public int $userId;

    public function __construct(int $conversationId, int $userId)
    {
        $this->conversationId = $conversationId;
        $this->userId = $userId;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('conversation.' . $this->conversationId),
        ];
    }
    
    public function broadcastAs(): string
    {
        return 'user.typing';
    }
}
