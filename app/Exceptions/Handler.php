<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Throwable;
use App\Http\Controllers\Controller;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        $controller = app(Controller::class);

        if ($exception instanceof ModelNotFoundException) {
            return $controller->notFound('Resource not found');
        }

        if ($exception instanceof AuthenticationException) {
            return $controller->unauthorized($exception->getMessage() ?: 'Unauthenticated');
        }

        if ($exception instanceof AuthorizationException) {
            return $controller->forbidden($exception->getMessage() ?: 'Forbidden');
        }

        if ($exception instanceof ValidationException) {
            return $controller->validationError('Validation failed', $exception->errors());
        }

        if ($exception instanceof ThrottleRequestsException) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests',
                'data' => null,
            ], 429, ['Retry-After' => $exception->getHeaders()['Retry-After'] ?? 60]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Server Error',
            'data' => null,
        ], 500);
    }
}