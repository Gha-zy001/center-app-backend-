<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, $type)
    {
        $key = $type . ':' . ($request->user()?->id ?? $request->ip());

        $limits = match($type) {
            'public' => ['max' => 30, 'minutes' => 1],
            'auth' => ['max' => 10, 'minutes' => 1],
            'user' => ['max' => 120, 'minutes' => 1],
            'admin' => ['max' => 300, 'minutes' => 1],
            'upload' => ['max' => 20, 'minutes' => 1],
            'search' => ['max' => 60, 'minutes' => 1],
            default => ['max' => 60, 'minutes' => 1],
        };

        if (RateLimiter::tooManyAttempts($key, $limits['max'])) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => trans('messages.too_many_requests', ['seconds' => $seconds]),
            ], 429)->header('Retry-After', $seconds);
        }

        RateLimiter::hit($key, $limits['minutes'] * 60);

        return $next($request);
    }
}