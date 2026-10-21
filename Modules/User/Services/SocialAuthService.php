<?php

namespace Modules\User\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Modules\User\app\Models\User;
use Modules\User\app\Models\SocialAccount;
use Spatie\Permission\Models\Role;


class SocialAuthService
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function getRedirectUrl(string $provider): string
    {
        return Socialite::driver($provider)
            ->stateless()
            ->redirect()
            ->getTargetUrl();
    }

    public function handleProviderCallback(string $provider): array
    {
        $socialUser = Socialite::driver($provider)->stateless()->user();
        
        if (!$socialUser->getEmail()) {
            throw new Exception(__('auth.social.email_required'), 422);
        }

        return DB::transaction(function () use ($provider, $socialUser) {
            
            $socialAccount = SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($socialAccount) {
                $user = $socialAccount->user;
            } else {
                $user = User::where('email', $socialUser->getEmail())->first();

                if ($user) {
                    $this->linkSocialAccount($user, $provider, $socialUser);
                } else {
                    $user = $this->createUserFromSocial($socialUser);
                    $this->linkSocialAccount($user, $provider, $socialUser);
                }
            }

            // Social login must respect account status exactly like password login does.
            $this->authService->ensureAccountAllowed($user);

            return $this->authService->getUserDataWithToken($user);
        });
    }

    private function linkSocialAccount(User $user, string $provider, $socialUser): void
    {
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
        ]);

    }

    private function createUserFromSocial($socialUser): User
    {
        // Same defaults as self-registration: an active, plain "user". The status column
        // defaults to "pending", which would otherwise lock every social sign-up out.
        $user = User::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
            'email' => $socialUser->getEmail(),
            'password' => Hash::make(Str::random(32)),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $user->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']));

        return $user;
    }

    public function unlinkSocialAccount(User $user, string $provider): void
    {
        $hasPassword = !is_null($user->password);
        $otherAccounts = $user->socialAccounts()->where('provider', '!=', $provider)->count();

        if (!$hasPassword && $otherAccounts === 0) {
            throw new Exception(__('auth.social.cannot_unlink_last_method'), 422);
        }

        $user->socialAccounts()
            ->where('provider', $provider)
            ->delete();
    }

    public function getUserSocialAccounts(User $user)
    {
        return $user->socialAccounts()
            ->select('provider', 'created_at')
            ->get();
    }
}