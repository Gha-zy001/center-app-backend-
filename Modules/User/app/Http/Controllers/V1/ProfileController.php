<?php

namespace Modules\User\app\Http\Controllers\V1;

use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Exception;
use App\Http\Controllers\Controller;
use Modules\User\Services\ProfileService;
use Modules\User\app\Http\Requests\DeleteAccountRequest;
use Modules\User\app\Http\Requests\UpdateProfileRequest;
use Modules\User\app\Http\Requests\UpdatePasswordRequest;
use Modules\User\app\Http\Requests\UpdatePreferencesRequest;
use Modules\User\app\Http\Requests\UploadAvatarRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService
    ) {}

    public function profile(Request $request): JsonResponse
    {
        try {
            return $this->successResponse(
                __('auth.profile_retrieved'),
                $this->profileService->getProfile($request->user())
            );
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(
                __('auth.profile_updated'),
                $this->profileService->updateProfile(
                    $request->user(), 
                    $request->validated()
                )
            );
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(
                __('auth.password_updated'),
                $this->profileService->updatePassword(
                    $request->user(),
                    $request->current_password,
                    $request->new_password
                )
            );
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(
                __('auth.avatar_uploaded'),
                $this->profileService->uploadAvatar(
                    $request->user(),
                    $request->file('avatar')
                )
            );
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        try {
            return $this->successResponse(
                __('auth.avatar_deleted'),
                $this->profileService->deleteAvatar($request->user())
            );
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function getPreferences(Request $request): JsonResponse
    {
        try {
            return $this->successResponse(
                __('auth.preferences_retrieved'),
                $this->profileService->getPreferences($request->user())
            );
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        try {
            return $this->successResponse(
                __('auth.preferences_updated'),
                $this->profileService->updatePreferences(
                    $request->user(),
                    $request->validated()
                )
            );
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }

    public function softDelete(DeleteAccountRequest $request): JsonResponse
    {
        try {
            $this->profileService->softDelete(
                $request->user(),
                $request->password
            );
            
            return $this->successResponse(__('auth.account_deleted'));
        } catch (ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), null, $this->getStatusCode($e));
        }
    }
}