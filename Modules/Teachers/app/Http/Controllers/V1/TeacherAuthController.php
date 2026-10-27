<?php

namespace Modules\Teachers\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Teachers\app\Contracts\TeacherAccessGate;
use Modules\Teachers\app\Http\Resources\TeacherResource;
use Modules\Teachers\app\Models\Teacher;

class TeacherAuthController extends Controller
{
    private const TOKEN_TTL_HOURS = 24;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $teacher = Teacher::where('username', strtolower(trim($credentials['username'])))->first();

        if (! $teacher || ! Hash::check($credentials['password'], $teacher->password)) {
            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        // Only reported after the password matched, so status is never leaked to a guesser.
        if (! $teacher->isActive()) {
            return $this->refuse(__('messages.account_inactive'), 'account_suspended');
        }

        $gate = app()->bound(TeacherAccessGate::class) ? app(TeacherAccessGate::class) : null;

        if ($gate && $reason = $gate->denial($teacher)) {
            return $this->refuse(__($reason), Str::after($reason, 'messages.'));
        }

        $teacher->forceFill(['last_login_at' => now()])->save();

        return $this->successResponse(__('auth.messages.logged_in_successfully'), [
            'teacher' => new TeacherResource($teacher),
            // e.g. "your package ends in less than 48 hours"; null when there is nothing to warn about.
            'notice' => $gate?->notice($teacher),
            'accessToken' => $teacher->createToken('teacher_token', ['*'], now()->addHours(self::TOKEN_TTL_HOURS))->plainTextToken,
            'tokenType' => 'Bearer',
            'accessExpiresIn' => self::TOKEN_TTL_HOURS * 3600,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $gate = app()->bound(TeacherAccessGate::class) ? app(TeacherAccessGate::class) : null;

        return $this->successResponse('OK', (new TeacherResource($teacher))->resolve() + ['notice' => $gate?->notice($teacher)]);
    }

    // A refusal carries a machine-readable `code` so the app can show the right screen without parsing the text.
    private function refuse(string $message, string $code): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null, 'code' => $code], 403);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(__('auth.messages.logged_out_successfully'));
    }
}
