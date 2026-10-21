<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Modules\User\app\Models\OtpCode;
use Modules\User\app\Models\RefreshToken;
use Modules\User\app\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTokenFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Pass1';

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function login(string $email = 'tester@example.com')
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
    }

    public function test_registration_ignores_roles_and_creates_a_plain_user(): void
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Newcomer',
            'email' => 'newcomer@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'roles' => ['super-admin'],
            'permissions' => ['delete-user'],
        ])->assertCreated();

        $user = User::where('email', 'newcomer@example.com')->firstOrFail();

        $this->assertSame(['user'], $user->getRoleNames()->all());
        $this->assertFalse($user->can('delete-user'));
        $this->assertFalse($user->canAccessPanel(\Filament\Facades\Filament::getDefaultPanel()));
        $response->assertJsonPath('data.user.roles', ['user']);
        $response->assertJsonPath('data.user.permissions', []);
    }

    public function test_login_and_profile_return_the_users_roles_and_permissions(): void
    {
        $user = $this->makeUser(['email' => 'member@example.com']);
        $role = Role::create(['name' => 'user', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::create(['name' => 'post-comments', 'guard_name' => 'web']));
        $user->assignRole($role);

        $login = $this->login('member@example.com')->assertOk();

        $login->assertJsonPath('data.user.roles', ['user']);
        $login->assertJsonPath('data.user.permissions', ['post-comments']);
        $this->assertFalse($user->fresh()->canAccessPanel(\Filament\Facades\Filament::getDefaultPanel()));

        $this->withToken($login->json('data.accessToken'))
            ->getJson('/api/v1/auth/profile')
            ->assertOk()
            ->assertJsonPath('data.permissions', ['post-comments']);
    }

    public function test_login_returns_access_and_refresh_tokens(): void
    {
        $this->makeUser();

        $this->login()->assertOk()->assertJsonStructure(['data' => ['accessToken', 'refreshToken', 'user']]);
    }

    public function test_refresh_token_can_only_be_used_once(): void
    {
        $this->makeUser();
        $refresh = $this->login()->json('data.refreshToken');

        $this->postJson('/api/v1/auth/refresh-token', ['refresh_token' => $refresh])->assertOk();
        $this->postJson('/api/v1/auth/refresh-token', ['refresh_token' => $refresh])->assertUnauthorized();
    }

    public function test_consume_is_atomic_for_a_single_token(): void
    {
        $user = $this->makeUser();
        $plain = RefreshToken::createToken($user);

        $this->assertNotNull(RefreshToken::consume($plain));
        $this->assertNull(RefreshToken::consume($plain));
    }

    public function test_refresh_is_rejected_for_banned_users(): void
    {
        $user = $this->makeUser();
        $refresh = $this->login()->json('data.refreshToken');
        $user->update(['status' => 'banned']);

        $this->postJson('/api/v1/auth/refresh-token', ['refresh_token' => $refresh])->assertStatus(422);
    }

    public function test_logout_revokes_refresh_tokens(): void
    {
        $this->makeUser();
        $login = $this->login()->json('data');

        $this->withToken($login['accessToken'])->postJson('/api/v1/auth/logout')->assertOk();

        $this->postJson('/api/v1/auth/refresh-token', ['refresh_token' => $login['refreshToken']])->assertUnauthorized();
    }

    public function test_login_is_blocked_for_non_active_accounts(): void
    {
        foreach (['banned', 'suspended', 'rejected', 'pending'] as $status) {
            $this->makeUser(['email' => "{$status}@example.com", 'status' => $status]);

            $this->login("{$status}@example.com")->assertStatus(422);
        }
    }

    public function test_password_reset_revokes_all_tokens(): void
    {
        $user = $this->makeUser();
        $login = $this->login()->json('data');

        Cache::put('reset_token:abc', $user->email, now()->addMinutes(5));
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'abc',
            'password' => 'New!Passw0rd9',
            'password_confirmation' => 'New!Passw0rd9',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/refresh-token', ['refresh_token' => $login['refreshToken']])->assertUnauthorized();
    }

    public function test_otp_verification_locks_out_after_five_wrong_codes(): void
    {
        $user = $this->makeUser(['email_verified_at' => null]);
        OtpCode::create(['email' => $user->email, 'type' => 'email_verification', 'code' => '123456', 'expires_at' => now()->addMinutes(10)]);

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/verify', ['email' => $user->email, 'code' => '000000'])->assertStatus(422);
        }

        // Even the correct code is refused once locked out.
        $this->postJson('/api/v1/auth/verify', ['email' => $user->email, 'code' => '123456'])->assertStatus(422);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_reset_and_resend_keep_their_explicit_responses(): void
    {
        $this->makeUser();
        $this->makeUser(['email' => 'unverified@example.com', 'email_verified_at' => null]);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(422);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'tester@example.com'])->assertOk()->assertJsonStructure(['data' => ['reset_code']]);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unverified@example.com'])->assertForbidden();
        $this->postJson('/api/v1/auth/resend-verification', ['email' => 'tester@example.com'])->assertStatus(422);
    }
}
