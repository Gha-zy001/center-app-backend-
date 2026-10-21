<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\User\app\Http\Controllers\V1\AuthController;
use Modules\User\app\Http\Controllers\V1\EmailVerificationController;
use Modules\User\app\Http\Controllers\V1\ForgotPasswordController;
use Modules\User\app\Http\Controllers\V1\ProfileController;
use Modules\User\app\Http\Controllers\V1\SocialAuthController;
use Modules\User\app\Http\Controllers\V1\UserController;

Route::prefix('v1/auth')->middleware([SetLocale::class])->group(function () {

    Route::post('register', [AuthController::class,'register'])->middleware('rate_limit:auth');
    Route::post('login', [AuthController::class,'login'])->middleware('rate_limit:auth');
    Route::post('resend-verification', [AuthController::class,'resendVerification'])->middleware('rate_limit:auth');
    Route::post('verify', [EmailVerificationController::class, 'verify'])->middleware('rate_limit:auth');
    Route::post('refresh-token', [AuthController::class,'refreshToken'])->middleware('rate_limit:user');

    Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword'])->middleware('rate_limit:auth');
    Route::post('verify-reset-code', [ForgotPasswordController::class, 'verifyResetCode'])->middleware('rate_limit:auth');
    Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword'])->middleware('rate_limit:auth');

    Route::post('forgot-password-link', [ForgotPasswordController::class, 'sendResetLink'])
        ->withoutMiddleware([SetLocale::class])
        ->middleware('rate_limit:auth');
    Route::get('reset-password/{token}', [ForgotPasswordController::class, 'verifyResetLink'])
        ->middleware('rate_limit:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class,'logout'])->middleware('rate_limit:user');

        // ========== PROFILE ROUTES ==========
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'profile'])->middleware('rate_limit:user');
            Route::post('/', [ProfileController::class, 'updateProfile'])->middleware('rate_limit:user');
            Route::post('/avatar', [ProfileController::class, 'uploadAvatar'])->middleware('rate_limit:upload');
            Route::delete('/avatar', [ProfileController::class, 'deleteAvatar'])->middleware('rate_limit:upload');
            Route::post('/password', [ProfileController::class, 'updatePassword'])->middleware('rate_limit:user');
            Route::get('/preferences', [ProfileController::class, 'getPreferences'])->middleware('rate_limit:user');
            Route::post('/preferences', [ProfileController::class, 'updatePreferences'])->middleware('rate_limit:user');
            Route::delete('/', [ProfileController::class, 'softDelete'])->middleware('rate_limit:user');
        });
        // ===================================

        Route::prefix('social')->group(function () {
            Route::get('accounts', [SocialAuthController::class, 'getLinkedAccounts'])->middleware('rate_limit:user');
            Route::delete('{provider}/unlink', [SocialAuthController::class, 'unlinkAccount'])->middleware('rate_limit:user');
        });

    });

    // Listing users is a back-office action: admin guard only, behind the view-users permission.
    Route::get('/users', [UserController::class, 'index'])
        ->middleware(['auth:sanctum-admin', EnsureAdmin::class, 'can:view-users', 'rate_limit:search']);

    Route::prefix('social')->group(function () {
        Route::post('redirect', [SocialAuthController::class, 'redirectToProvider'])->middleware('rate_limit:auth');
    });
});

Route::get('/login/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback'])
   ->withoutMiddleware([SetLocale::class])
   ->whereIn('provider', ['google', 'facebook'])
   ->middleware('rate_limit:auth');