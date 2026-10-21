<?php

namespace Modules\User\app\Http\Controllers\V1;

use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\User\app\Http\Requests\VerifyOtpRequest;
use Modules\User\Services\AuthService;

class EmailVerificationController extends Controller
{
    public function __construct(private AuthService $authService) {}
    
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->verifyEmailOtp($request->email, $request->code);
            
            if (!$data) {
                return $this->errorResponse(__('auth.invalid_verification_code'), null, 422);
            }

            return $this->successResponse(__('auth.messages.email_verified_successfully'), $data);
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }
}
