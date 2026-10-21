<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{

    protected function perPage(Request $request, string $key = 'per_page', int $default = 10, int $max = 100): int
    {
        $value = $request->input($key);

        return max(1, min(is_numeric($value) ? (int) $value : $default, $max));
    }

    protected function getStatusCode(Exception $e): int
    {
        $code = $e->getCode();
        
        if (is_int($code) && $code >= 100 && $code <= 599) {
            return $code;
        }
        
        return 400;
    }

    public function successResponse(string $message, $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public function errorResponse(string $message, $data = null, ?int $code = 400): JsonResponse
    {

        $statusCode = is_int($code) ? $code : 400;

        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public function notFound(string $message, $data = null): JsonResponse
    {
        return $this->errorResponse($message, $data, 404);
    }

    public function unauthorized(string $message, $data = null): JsonResponse
    {
        return $this->errorResponse($message, $data, 401);
    }

    public function forbidden(string $message, $data = null): JsonResponse
    {
        return $this->errorResponse($message, $data, 403);
    }

    public function validationError(string $message, $errors = null): JsonResponse
    {
        return $this->errorResponse($message, $errors, 422);
    }

    public function paginated($paginator, $resource, string $message): JsonResponse
    {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
            'data' => $resource::collection(collect($paginator->items()))->resolve(),
        ], $this->paginationMeta($paginator)));
    }

    /**
     * @return array{meta: array<string, int>, links: array<string, ?string>}
     */
    protected function paginationMeta($paginator): array
    {
        return [
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }
}