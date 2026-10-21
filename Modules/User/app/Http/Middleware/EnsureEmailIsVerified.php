<?php

namespace Modules\User\app\Http\Middleware;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() ||
            ($request->user() instanceof MustVerifyEmail &&
            ! $request->user()->hasVerifiedEmail()) ||
            ! $request->user()->email_verified_at) {
            
            return response()->json([
                'success' => false,
                'message' => __('auth.messages.email_not_verified') ?? 'Your email address is not verified.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
