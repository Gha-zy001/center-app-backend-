<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Authorization\app\Models\Admin;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense in depth for admin API routes: the authenticated principal must be an Admin,
 * whichever guard resolved it.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof Admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        return $next($request);
    }
}
