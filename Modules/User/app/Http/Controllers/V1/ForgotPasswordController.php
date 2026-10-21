<?php

namespace Modules\User\app\Http\Controllers\V1;

use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\User\app\Http\Requests\ForgotPasswordRequest;
use Modules\User\app\Http\Requests\ResetPasswordRequest;
use Modules\User\app\Http\Requests\VerifyOtpRequest;
use Modules\User\app\Http\Requests\VerifyResetCodeRequest;
use Modules\User\Services\AuthService;

class ForgotPasswordController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->sendResetCode($request->email);
            return $this->successResponse(__('auth.messages.reset_code_sent'), $data);
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function verifyResetCode(VerifyResetCodeRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->verifyResetCode($request->email, $request->code);
            return $this->successResponse(__('auth.code_verified_successfully'), ['token' => $token]);
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword($request->token, $request->password);
            return $this->successResponse(__('auth.password_reset_success'));
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }


    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->sendResetLink($request->email);
            return $this->successResponse(__('auth.messages.reset_link_sent'), $data);
        } catch (AuthorizationException $e) {
            return $this->errorResponse(__('auth.messages.email_not_verified'), null, 403);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function verifyResetLink(Request $request, string $token): JsonResponse
    {
        try {
            $email = $this->authService->verifyResetLink($token);
            return $this->successResponse(__('auth.messages.link_verified'), ['email' => $email]);
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

  


}
