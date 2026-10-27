<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\Packages\app\Http\Controllers\V1\AdminPackageController;
use Modules\Packages\app\Http\Controllers\V1\AdminSubscriptionController;
use Modules\Packages\app\Http\Controllers\V1\TeacherSubscriptionController;
use Modules\Teachers\app\Http\Middleware\ResolveTeacherContext;

Route::prefix('v1')->middleware([SetLocale::class])->group(function () {

    // The center admin manages packages and gives them to teachers, each route behind its own permission.
    Route::prefix('admin')
        ->middleware(['auth:sanctum-admin', EnsureAdmin::class, 'rate_limit:admin'])
        ->group(function () {
            Route::get('packages', [AdminPackageController::class, 'index'])->middleware('can:view-packages');
            Route::post('packages', [AdminPackageController::class, 'store'])->middleware('can:create-package');
            Route::get('packages/{package}', [AdminPackageController::class, 'show'])->middleware('can:view-packages');
            Route::put('packages/{package}', [AdminPackageController::class, 'update'])->middleware('can:edit-package');
            Route::delete('packages/{package}', [AdminPackageController::class, 'destroy'])->middleware('can:delete-package');

            Route::get('subscriptions', [AdminSubscriptionController::class, 'index'])->middleware('can:view-packages');
            Route::get('teachers/{teacher}/subscription', [AdminSubscriptionController::class, 'show'])->middleware('can:view-packages');
            Route::post('teachers/{teacher}/subscription', [AdminSubscriptionController::class, 'store'])->middleware('can:assign-package');
        });

    // A teacher sees their own package (the account stays reachable for the "expired" message through login only).
    Route::prefix('teacher')
        ->middleware(['auth:sanctum-teacher', ResolveTeacherContext::class, 'rate_limit:user'])
        ->group(function () {
            Route::get('subscription', [TeacherSubscriptionController::class, 'show']);
        });
});
