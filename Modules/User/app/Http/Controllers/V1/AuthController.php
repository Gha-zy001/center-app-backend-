<?php

namespace Modules\User\app\Http\Controllers\V1;

use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Modules\User\app\Http\Requests\LoginRequest;
use Modules\User\app\Http\Requests\RefreshTokenRequest;
use Modules\User\app\Http\Requests\RegisterRequest;
use Modules\User\app\Http\Requests\ResendVerificationRequest;
use Modules\User\Services\AuthService;
use App\Http\Controllers\Controller; 

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->register($request->validated());
            return $this->successResponse(__('auth.messages.registered_successfully'), $data, 201);
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Auth Error: ' . $e->getMessage());
            return $this->errorResponse(__('messages.unexpected_error'), null, $this->getStatusCode($e));
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->login($request->validated());
            return $this->successResponse(__('auth.messages.logged_in_successfully'), $data);
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Auth Error: ' . $e->getMessage());
            return $this->errorResponse(__('messages.unexpected_error'), null, $this->getStatusCode($e));
        }
    }

    public function logout(): JsonResponse
    {
        try {
            $this->authService->logout(auth()->user());
            return $this->successResponse(__('auth.messages.logged_out_successfully'));
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Auth Error: ' . $e->getMessage());
            return $this->errorResponse(__('messages.unexpected_error'), null, $this->getStatusCode($e));
        }
    }

    public function refreshToken(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->refreshToken($request->refresh_token);
            return $this->successResponse(__('auth.token_refreshed_successfully'), $data);
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Auth Error: ' . $e->getMessage());
            return $this->errorResponse(__('messages.unexpected_error'), null, $this->getStatusCode($e));
        }
    }

    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->resendVerification($request->email);
            return $this->successResponse(__('auth.messages.verification_code_sent'), $data);
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Auth Error: ' . $e->getMessage());
            return $this->errorResponse(__('An unexpected error occurred.'), null, $this->getStatusCode($e));
        }
    }
}