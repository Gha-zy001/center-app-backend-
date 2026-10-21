<?php

namespace Modules\UserNotifications\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\UserNotifications\app\Interfaces\NotificationServiceInterface;
use Modules\UserNotifications\app\Transformers\UserNotificationResource;

class UserNotificationsController extends Controller
{
    protected NotificationServiceInterface $notificationService;

    public function __construct(NotificationServiceInterface $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function registerToken(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
            'device_type' => 'required|string|in:android,ios,web',
        ]);

        $this->notificationService->registerToken(auth()->id(), $request->device_token, $request->device_type);

        return $this->successResponse(__('notifications.token_registered'));
    }

    public function index(Request $request)
    {
        $perPage = $this->perPage($request);
        $notifications = $this->notificationService->getNotifications(auth()->id(), $perPage);

        return $this->paginated(
            $notifications, 
            UserNotificationResource::class, 
            __('notifications.notifications_fetched')
        );
    }

    public function markAsRead(int $id)
    {
        $this->notificationService->markAsRead($id, auth()->id());

        return $this->successResponse(__('notifications.notification_marked_read'));
    }

    public function markAllAsRead()
    {
        $this->notificationService->markAllAsRead(auth()->id());

        return $this->successResponse(__('notifications.all_notifications_marked_read'));
    }

    public function destroy(int $id)
    {
        $this->notificationService->deleteNotification($id, auth()->id());

        return $this->successResponse(__('notifications.notification_deleted'));
    }

    public function unreadCount()
    {
        $count = $this->notificationService->getUnreadCount(auth()->id());

        return $this->successResponse(__('notifications.unread_notifications_count'), ['count' => $count]);
    }
}
