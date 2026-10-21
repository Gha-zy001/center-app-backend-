<?php

namespace Modules\Teachers\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Teachers\app\Models\Teacher;
use Modules\Teachers\app\Support\TeacherContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the bearer token on the teacher guard and opens that teacher's context for
 * the request. The teacher identity comes only from the token, never from a route
 * parameter, query string, body or header. A suspended teacher is refused on every request,
 * so suspending takes effect immediately even for tokens already issued.
 */
class ResolveTeacherContext
{
    public function __construct(private readonly TeacherContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $teacher = $request->user('sanctum-teacher');

        if (! $teacher instanceof Teacher) {
            return $this->deny('Unauthenticated.', 401);
        }

        if (! $teacher->isActive()) {
            return $this->deny(__('messages.account_inactive'), 403);
        }

        $request->setUserResolver(fn () => $teacher);
        $this->context->set($teacher);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null], $status);
    }
}
