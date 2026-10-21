<?php

use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\User\app\Http\Middleware\CheckUserStatus;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Registers POST /broadcasting/auth for the API (Sanctum token) and loads channel authorization.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'SetLocale' => SetLocale::class,
            'rate_limit' => RateLimitMiddleware::class,
            'CheckStatus' => CheckUserStatus::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
