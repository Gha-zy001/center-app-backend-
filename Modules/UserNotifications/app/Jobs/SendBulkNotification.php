<?php

namespace Modules\UserNotifications\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\UserNotifications\app\Interfaces\NotificationServiceInterface;

/**
 * Sends one admin broadcast to a chunk of users, off the HTTP request.
 */
class SendBulkNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  array<int, int>  $userIds
     */
    public function __construct(
        public array $userIds,
        public string $title,
        public string $body,
        public string $type = 'admin_broadcast',
    ) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(NotificationServiceInterface $notificationService): void
    {
        foreach ($this->userIds as $userId) {
            $notificationService->sendNotification($userId, $this->title, $this->body, $this->type);
        }
    }
}
