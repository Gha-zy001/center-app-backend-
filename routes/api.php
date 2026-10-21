<?php

use App\Http\Controllers\Api\MediaController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\UserNotifications\app\Interfaces\NotificationServiceInterface;

// Generic media management attaches files to arbitrary models, so it is a back-office tool.
Route::prefix('v1/media')
    ->middleware([SetLocale::class, 'auth:sanctum-admin', EnsureAdmin::class, 'rate_limit:upload'])
    ->group(function () {
        Route::post('/', [MediaController::class, 'store']);
        Route::get('{id}', [MediaController::class, 'show']);
        Route::delete('{id}', [MediaController::class, 'destroy']);
        Route::get('{id}/download', [MediaController::class, 'download']);
    });

// Manual push test: admins only, so it cannot be used to spam users.
Route::get('v1/test-send/{userId}', function (int $userId) {
    $notificationService = app(NotificationServiceInterface::class);

    $title = 'اختبار إشعار';
    $body = 'هذا إشعار تجريبي لكل الأجهزة المسجلة لهذا المستخدم';

    $notificationService->sendNotification($userId, $title, $body);

    return response()->json([
        'success' => true,
        'message' => 'تم إرسال الإشعار بنجاح'
    ]);
})->middleware(['auth:sanctum-admin', EnsureAdmin::class, 'rate_limit:admin'])->whereNumber('userId');
