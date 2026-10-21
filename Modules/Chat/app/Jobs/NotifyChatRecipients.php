<?php

namespace Modules\Chat\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Chat\app\Models\Participant;
use Modules\UserNotifications\app\Interfaces\NotificationServiceInterface;

class NotifyChatRecipients implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $conversationId,
        public int $senderId,
        public string $title,
        public string $body,
    ) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(NotificationServiceInterface $notificationService): void
    {
        $recipientIds = Participant::where('conversation_id', $this->conversationId)
            ->where('user_id', '!=', $this->senderId)
            ->pluck('user_id');

        foreach ($recipientIds as $recipientId) {
            $notificationService->sendNotification($recipientId, $this->title, $this->body, 'chat_message');
        }
    }
}
