<?php

namespace Modules\UserNotifications\app\Services;

use Throwable;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Modules\UserNotifications\app\Models\UserFcmToken;
use Modules\UserNotifications\app\Models\UserNotification;
use Modules\UserNotifications\app\Interfaces\NotificationServiceInterface;

class NotificationService implements NotificationServiceInterface
{
   
    public function registerToken(int $userId, string $token, string $deviceType): void
    {
        UserFcmToken::updateOrCreate(
            ['user_id' => $userId, 'device_token' => $token],
            ['device_type' => $deviceType]
        );
    }

    public function sendNotification(int $userId, string $title, string $body, string $type = 'custom'): bool
    {
        $notification = UserNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ]);

        $tokens = UserFcmToken::where('user_id', $userId)->pluck('device_token')->toArray();

        if (!empty($tokens)) {
            $messaging = app('firebase.messaging');

            $message = CloudMessage::new()
                ->withNotification(FcmNotification::create($title, $body));
            
            try {
                $report = $messaging->sendMulticast($message, $tokens);

                $successCount = $report->successes()->count();
                $failureCount = $report->failures()->count();

                Log::info("FCM Success: {$successCount}");
                Log::info("FCM Failures: {$failureCount}");

                foreach ($report->failures()->getItems() as $failure) {
                    Log::error('FCM Error: ' . $failure->error()->getMessage());
                }
                if ($successCount === 0) {
                    return false;
                }
            } catch (Throwable $e) {
                Log::error('Firebase send error: '.$e->getMessage());
                return false;
            }
        }

        return true;
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = UserNotification::where('user_id', $userId)->findOrFail($notificationId);
        $notification->update(['read_at' => now()]);
        return true;
    }

    public function markAllAsRead(int $userId): bool
    {
        UserNotification::where('user_id', $userId)->whereNull('read_at')->update(['read_at' => now()]);
        return true;
    }

    public function deleteNotification(int $notificationId, int $userId): bool
    {
        return UserNotification::where('id', $notificationId)->where('user_id', $userId)->delete() > 0;
    }

    public function getNotifications(int $userId, int $perPage = 10)
    {
        return UserNotification::where('user_id', $userId)->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getUnreadCount(int $userId): int
    {
        return UserNotification::where('user_id', $userId)->whereNull('read_at')->count();
    }
    
}
