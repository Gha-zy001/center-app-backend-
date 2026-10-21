<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\Settings\app\Http\Controllers\V1\SettingController;

Route::prefix('v1/settings')->middleware([SetLocale::class])->group(function () {

    // Public settings are readable by anyone (the service only returns is_public = true).
    Route::get('/', [SettingController::class, 'index'])->middleware('rate_limit:public');

    // Changing settings is an admin action.
    Route::middleware(['auth:sanctum-admin', EnsureAdmin::class, 'can:manage-settings', 'rate_limit:admin'])->group(function () {
        Route::post('{key}', [SettingController::class, 'update']);
        Route::put('/', [SettingController::class, 'bulkUpdate']);
    });

});
