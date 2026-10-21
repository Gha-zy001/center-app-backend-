<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\Teachers\app\Http\Controllers\V1\AdminTeacherBotController;
use Modules\Teachers\app\Http\Controllers\V1\AdminTeacherController;
use Modules\Teachers\app\Http\Controllers\V1\TeacherAuthController;
use Modules\Teachers\app\Http\Middleware\ResolveTeacherContext;

Route::prefix('v1')->middleware([SetLocale::class])->group(function () {

    // Center admin manages teachers: admins only, each route behind its own permission.
    Route::prefix('admin/teachers')
        ->middleware(['auth:sanctum-admin', EnsureAdmin::class, 'rate_limit:admin'])
        ->group(function () {
            Route::get('/', [AdminTeacherController::class, 'index'])->middleware('can:view-teachers');
            Route::post('/', [AdminTeacherController::class, 'store'])->middleware('can:create-teacher');
            Route::get('/{teacher}', [AdminTeacherController::class, 'show'])->middleware('can:view-teachers');
            Route::post('/{teacher}', [AdminTeacherController::class, 'update'])->middleware('can:edit-teacher');
            Route::delete('/{teacher}', [AdminTeacherController::class, 'destroy'])->middleware('can:delete-teacher');

            Route::post('/{teacher}/activate', [AdminTeacherController::class, 'activate'])->middleware('can:change-teacher-status');
            Route::post('/{teacher}/deactivate', [AdminTeacherController::class, 'deactivate'])->middleware('can:change-teacher-status');
            Route::get('/{teacher}/bot', [AdminTeacherBotController::class, 'show'])->middleware('can:view-teachers');
            Route::put('/{teacher}/bot', [AdminTeacherBotController::class, 'update'])->middleware('can:edit-teacher');
            Route::post('/{teacher}/password', [AdminTeacherController::class, 'updatePassword'])->middleware('can:edit-teacher');
        });

    // Teacher login and session. Teachers live in their own table and use the "sanctum-teacher" guard.
    Route::prefix('teacher/auth')->group(function () {
        Route::post('login', [TeacherAuthController::class, 'login'])->middleware('rate_limit:auth');

        Route::middleware(['auth:sanctum-teacher', ResolveTeacherContext::class, 'rate_limit:user'])->group(function () {
            Route::get('me', [TeacherAuthController::class, 'me']);
            Route::post('logout', [TeacherAuthController::class, 'logout']);
        });
    });
});
