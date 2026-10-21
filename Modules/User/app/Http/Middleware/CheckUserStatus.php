<?php

namespace Modules\User\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.account_pending_approval'),
                    'data' => null
                ], 403);
            }
            

            if ($user->isRejected()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.account_rejected'),
                    'data' => null
                ], 403);
            }
            
            if ($user->isSuspended()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.account_suspended'),
                    'data' => null
                ], 403);
            }
            
            if ($user->isBanned()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.account_banned'),
                    'data' => null
                ], 403);
            }
        }

        return $next($request);
    }
}