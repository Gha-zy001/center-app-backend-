<?php

namespace Modules\User\Services;

use Exception;
use Throwable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\User\app\Models\OtpCode;
use Modules\User\app\Models\RefreshToken;
use Modules\User\app\Models\User;
use Modules\User\app\Transformers\AuthUserResource;
use Spatie\Permission\Models\Role;

class AuthService
{
    public function register(array $data): array
    {
        $user = $this->createUser($data);
        $code = $this->sendVerificationEmail($user);

        return [
            'user' => new AuthUserResource($user),
            'verificationCode' => $code
        ];
    }

    private function createUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'active',

        ]);

        // Self-registered accounts are always plain users; admin roles are assigned by admins only.
        $user->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']));

        return $user;
    }
    public function login(array $data): array
    {
        // Always check user credentials against the users table, whatever the current default guard is.
        if (!Auth::guard('web')->attempt($data)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $user = Auth::guard('web')->user();

       /* if ($user->status !== 'active') {
            Auth::logout();
            throw ValidationException::withMessages([
                'status' => __('auth.account_' . $user->status)
            ]);
        }*/
        $this->ensureAccountAllowed($user);

        if (!$user->email_verified_at) {
            $code = $this->sendVerificationEmail($user);
            throw ValidationException::withMessages([
                'email' => __('auth.messages.email_not_verified'),
            ]);
        }

        return $this->getUserDataWithToken($user);
    }

    public function logout($user): void
    {
        $user->tokens()->delete();
        RefreshToken::revokeAllFor($user);
    }

    public function refreshToken(string $token): array
    {
        $refreshToken = RefreshToken::consume($token);
        if (!$refreshToken) {
            throw new Exception(__('auth.invalid_or_expired_refresh_token'), 401);
        }

        $user = $refreshToken->user;
        if (!$user) {
            throw new Exception(__('auth.invalid_or_expired_refresh_token'), 401);
        }

        $this->ensureAccountAllowed($user);

        return $this->getUserDataWithToken($user);
    }

    public function resendVerification(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();

        if ($user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => __('auth.messages.email_already_verified')
            ]);
        }

        $code = $this->sendVerificationEmail($user);
        return ['verificationCode' => $code];
    }

    private const BLOCKED_STATUS_MESSAGES = [
        'pending' => 'auth.account_pending_approval',
        'rejected' => 'auth.account_rejected',
        'suspended' => 'auth.account_suspended',
        'banned' => 'auth.account_banned',
    ];

    public function ensureAccountAllowed(User $user): void
    {
        $key = self::BLOCKED_STATUS_MESSAGES[$user->status] ?? null;

        if ($key) {
            throw ValidationException::withMessages(['status' => __($key)]);
        }
    }

    private function otpAttemptKey(string $type, string $email): string
    {
        return "otp_attempts:{$type}:" . strtolower($email);
    }

    private function ensureOtpAttemptsAvailable(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => __('messages.too_many_requests', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
    }

    public function getUserDataWithToken(User $user): array
    {
        $accessToken = $user->createToken('auth_token', ['*'], now()->addHours(2))->plainTextToken;
        $refreshToken = RefreshToken::createToken($user);

        return [
            'user' => new AuthUserResource($user),
            'accessToken' => $accessToken,
            'tokenType' => 'Bearer',
            'accessExpiresIn' => 7200,
            'refreshToken' => $refreshToken,
            'refreshExpiresIn' => 2592000,
        ];
    }

    private function sendVerificationEmail(User $user): string
    {
        try {
            $code = random_int(100000, 999999);

            OtpCode::updateOrCreate(
                ['email' => $user->email, 'type' => 'email_verification'],
                ['code' => $code, 'expires_at' => now()->addMinutes(10)]
            );

            return $code;
        } catch (Throwable $e) {
            Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function verifyEmailOtp(string $email, string $code): bool
    {
        $attemptKey = $this->otpAttemptKey('email_verification', $email);
        $this->ensureOtpAttemptsAvailable($attemptKey);

        $otp = OtpCode::where('email', $email)
            ->where('type', 'email_verification')
            ->where('code', $code)
            ->where('expires_at', '>=', now())
            ->first();

        if (!$otp) {
            RateLimiter::hit($attemptKey, 600);
            throw ValidationException::withMessages(['code' => __('auth.invalid_verification_code')]);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            throw ValidationException::withMessages(['email' => __('auth.user_not_found')]);
        }

        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        $otp->delete();
        RateLimiter::clear($attemptKey);
        return true;
    }

    public function sendResetCode(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();

        if (!$user->email_verified_at) {
            throw new AuthorizationException(__('auth.messages.email_not_verified'));
        }

        $code = random_int(100000, 999999);

        OtpCode::updateOrCreate(
            ['email' => $user->email, 'type' => 'password_reset'],
            ['code' => $code, 'expires_at' => now()->addMinutes(10)]
        );

        return ['reset_code' => $code];
    }

    public function verifyResetCode(string $email, string $code): string
    {
        $user = User::where('email', $email)->firstOrFail();

        $attemptKey = $this->otpAttemptKey('password_reset', $email);
        $this->ensureOtpAttemptsAvailable($attemptKey);

        $otp = OtpCode::where('email', $email)
            ->where('type', 'password_reset')
            ->where('code', $code)
            ->where('expires_at', '>=', now())
            ->first();

        if (!$otp) {
            RateLimiter::hit($attemptKey, 600);
            throw ValidationException::withMessages(['code' => __('auth.invalid_reset_code')]);
        }

        $otp->delete();
        RateLimiter::clear($attemptKey);
        return $this->generateResetToken($email);
    }

    public function resetPassword(string $token, string $password): void
    {
        $email = Cache::get("reset_token:{$token}");

        if (!$email) {
            throw ValidationException::withMessages(['token' => __('auth.invalid_or_expired_token')]);
        }

        $user = User::where('email', $email)->firstOrFail();
        $user->update(['password' => Hash::make($password)]);
        Cache::forget("reset_token:{$token}");

        $user->tokens()->delete();
        RefreshToken::revokeAllFor($user);
    }

    private function generateResetToken(string $email): string
    {
        $token = Str::random(60);
        Cache::put("reset_token:{$token}", $email, now()->addDay());
        return $token;
    }


    public function sendResetLink(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();

        if (!$user->email_verified_at) {
            throw new AuthorizationException(__('auth.messages.email_not_verified'));
        }

        $token = Str::random(60);
        $expiresAt = now()->addHour();

        Cache::put("password_reset_link:{$token}", $email, $expiresAt);

        $resetLink = url("/reset-password/{$token}");

        // Mail::to($user)->send(new ResetPasswordMail($resetLink));

        return [
            'reset_link' => $resetLink,
            'expires_at' => $expiresAt,
        ];
    }

    public function verifyResetLink(string $token): string
    {
        $email = Cache::get("password_reset_link:{$token}");

        if (!$email) {
            throw ValidationException::withMessages(['token' => __('auth.invalid_or_expired_link')]);
        }

        return $email;
    }


   /* public function resetPassword(string $token, string $password): void
    {
        $email = Cache::get("password_reset_link:{$token}");

        if (!$email) {
            throw ValidationException::withMessages(['token' => __('auth.invalid_or_expired_token')]);
        }

        $user = User::where('email', $email)->firstOrFail();
        $user->update(['password' => Hash::make($password)]);

        Cache::forget("password_reset_link:{$token}");
    }*/

}