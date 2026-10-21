<?php

namespace Modules\Authorization\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Authorization\app\Http\Resources\AdminResource;
use Modules\Authorization\app\Models\Admin;

class AdminAuthController extends Controller
{
    private const TOKEN_TTL_HOURS = 2;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::where('email', $credentials['email'])->first();

        if (! $admin || ! Hash::check($credentials['password'], $admin->password) || ! $admin->is_active) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $admin->forceFill(['last_login_at' => now()])->save();

        return $this->successResponse(__('auth.messages.logged_in_successfully'), [
            'admin' => new AdminResource($admin),
            'accessToken' => $admin->createToken('admin_token', ['*'], now()->addHours(self::TOKEN_TTL_HOURS))->plainTextToken,
            'tokenType' => 'Bearer',
            'accessExpiresIn' => self::TOKEN_TTL_HOURS * 3600,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse('OK', new AdminResource($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->successResponse(__('auth.messages.logged_out_successfully'));
    }
}
