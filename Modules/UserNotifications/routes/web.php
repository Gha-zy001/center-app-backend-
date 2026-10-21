<?php

use Illuminate\Support\Facades\Route;
use Modules\UserNotifications\Http\Controllers\UserNotificationsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('usernotifications', UserNotificationsController::class)->names('usernotifications');
});
