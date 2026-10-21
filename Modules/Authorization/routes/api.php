<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\Authorization\app\Http\Controllers\V1\AdminAuthController;
use Modules\Authorization\app\Http\Controllers\V1\RoleController;
use Modules\Authorization\app\Http\Controllers\V1\UserController;
use Modules\Authorization\app\Http\Controllers\V1\UserRoleController;

Route::prefix('v1')->middleware([SetLocale::class])->group(function () {

    // Admin login and session. Admins live in their own table and use the "sanctum-admin" guard.
    Route::prefix('admin/auth')->group(function () {
        Route::post('login', [AdminAuthController::class, 'login'])->middleware('rate_limit:auth');

        Route::middleware(['auth:sanctum-admin', EnsureAdmin::class, 'rate_limit:admin'])->group(function () {
            Route::get('me', [AdminAuthController::class, 'me']);
            Route::post('logout', [AdminAuthController::class, 'logout']);
        });
    });

    // Back-office management: admins only, each route behind its own permission.
    Route::middleware(['auth:sanctum-admin', EnsureAdmin::class, 'rate_limit:admin'])->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->middleware('can:view-roles');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('can:create-role');
        Route::post('/roles/{role}', [RoleController::class, 'update'])->middleware('can:edit-role');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('can:delete-role');

        // Roles and permissions for regular users (web guard). Admin roles are under /roles.
        Route::middleware('can:manage-user-roles')->group(function () {
            Route::get('/user-roles', [UserRoleController::class, 'index']);
            Route::post('/user-roles', [UserRoleController::class, 'store']);
            Route::post('/user-roles/{role}', [UserRoleController::class, 'update']);
            Route::delete('/user-roles/{role}', [UserRoleController::class, 'destroy']);

            Route::get('/user-permissions', [UserRoleController::class, 'permissions']);
            Route::post('/user-permissions', [UserRoleController::class, 'storePermission']);
            Route::delete('/user-permissions/{permission}', [UserRoleController::class, 'destroyPermission']);
        });

        Route::get('/users', [UserController::class, 'index'])->middleware('can:view-users');
        Route::post('/users', [UserController::class, 'store'])->middleware('can:create-user');
        Route::post('/users/{user}', [UserController::class, 'update'])->middleware('can:edit-user');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('can:delete-user');
        Route::post('users/{user}/status', [UserController::class, 'updateStatus'])->middleware('can:edit-user');
    });
});
