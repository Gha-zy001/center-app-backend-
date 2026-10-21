<?php

namespace Modules\User\app\Http\Controllers\V1;

use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Exception;
use App\Http\Controllers\Controller;
use Modules\User\Services\SocialAuthService;
use Modules\User\app\Http\Requests\SocialLoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialAuthController extends Controller
{
    public function __construct(
        private SocialAuthService $socialAuthService
    ) {}

    public function redirectToProvider(SocialLoginRequest $request): JsonResponse
    {
        try {
            $redirectUrl = $this->socialAuthService->getRedirectUrl($request->provider);
            
            return $this->successResponse(
                __('auth.messages.redirect_url_retrieved'),
                ['redirectUrl' => $redirectUrl]
            );
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function handleProviderCallback(Request $request, string $provider): JsonResponse
    {
        try {
            $data = $this->socialAuthService->handleProviderCallback($provider);
            
            return $this->successResponse(
                __('auth.messages.logged_in_successfully'),
                $data
            );
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function getLinkedAccounts(Request $request): JsonResponse
    {
        try {
            $accounts = $this->socialAuthService->getUserSocialAccounts($request->user());
            
            return $this->successResponse(
                __('auth.social.accounts_retrieved'),
                $accounts
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 400);
        }
    }

    public function unlinkAccount(Request $request, string $provider): JsonResponse
    {
        try {
            $this->socialAuthService->unlinkSocialAccount($request->user(), $provider);
            
            return $this->successResponse(
                __('auth.social.account_unlinked', ['provider' => $provider])
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }
    }
}