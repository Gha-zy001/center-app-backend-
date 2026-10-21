<?php

namespace Modules\UserNotifications\app\Interfaces;

interface NotificationServiceInterface {
    
    public function registerToken(int $userId, string $token, string $deviceType): void;

    public function sendNotification(int $userId, string $title, string $body, string $type = 'custom'): bool;

    public function markAsRead(int $notificationId, int $userId): bool;

    public function markAllAsRead(int $userId): bool;

    public function deleteNotification(int $notificationId, int $userId): bool;

    public function getNotifications(int $userId, int $perPage = 10);

    public function getUnreadCount(int $userId): int;
}
