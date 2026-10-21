<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\UserNotifications\app\Http\Controllers\UserNotificationsController;

Route::prefix('v1/notifications')->middleware([SetLocale::class, 'auth:sanctum'])->group(function () {
    Route::post('/token', [UserNotificationsController::class, 'registerToken']);
    Route::get('/', [UserNotificationsController::class, 'index']);
    Route::put('{id}/read', [UserNotificationsController::class, 'markAsRead']);
    Route::put('read-all', [UserNotificationsController::class, 'markAllAsRead']);
    Route::delete('{id}', [UserNotificationsController::class, 'destroy']);
    Route::get('unread-count', [UserNotificationsController::class, 'unreadCount']);
});