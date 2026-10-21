<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\Authorization\app\Models\Admin;
use Modules\User\app\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSeparationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Pass1';

    private function makeAdmin(array $permissions = [], array $overrides = []): Admin
    {
        $admin = Admin::create(array_merge([
            'name' => 'Boss',
            'email' => 'boss@example.com',
            'password' => Hash::make(self::PASSWORD),
        ], $overrides));

        foreach ($permissions as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']));
        }

        return $admin;
    }

    private function makeUser(string $email = 'member@example.com'): User
    {
        return User::create([
            'name' => 'Member',
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_admins_and_users_live_in_different_tables(): void
    {
        $this->makeAdmin();
        $this->makeUser();

        $this->assertDatabaseHas('admins', ['email' => 'boss@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'boss@example.com']);
        $this->assertDatabaseMissing('admins', ['email' => 'member@example.com']);
    }

    public function test_admin_can_log_in_through_the_admin_api_and_gets_roles_and_permissions(): void
    {
        $admin = $this->makeAdmin(['view-users']);
        $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'admin']));

        $response = $this->postJson('/api/v1/admin/auth/login', ['email' => 'boss@example.com', 'password' => self::PASSWORD]);

        $response->assertOk()
            ->assertJsonPath('data.admin.roles', ['admin'])
            ->assertJsonPath('data.admin.permissions', ['view-users']);
        $this->assertNotNull($admin->fresh()->last_login_at);

        $this->withToken($response->json('data.accessToken'))
            ->getJson('/api/v1/admin/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'boss@example.com');
    }

    public function test_admin_credentials_do_not_work_on_the_user_login(): void
    {
        $this->makeAdmin();

        $this->postJson('/api/v1/auth/login', ['email' => 'boss@example.com', 'password' => self::PASSWORD])
            ->assertStatus(422);
    }

    public function test_user_credentials_do_not_work_on_the_admin_login(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/admin/auth/login', ['email' => 'member@example.com', 'password' => self::PASSWORD])
            ->assertStatus(422);
    }

    public function test_inactive_admin_cannot_log_in_or_open_the_panel(): void
    {
        $admin = $this->makeAdmin([], ['is_active' => false]);

        $this->postJson('/api/v1/admin/auth/login', ['email' => 'boss@example.com', 'password' => self::PASSWORD])
            ->assertStatus(422);
        $this->assertFalse($admin->canAccessPanel(Filament::getDefaultPanel()));
    }

    public function test_user_token_is_rejected_on_admin_routes(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/v1/roles')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_admin_token_is_rejected_on_user_routes(): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/profile')->assertUnauthorized();
    }

    public function test_admin_management_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/roles')->assertUnauthorized();
        $this->postJson('/api/v1/roles', ['name' => 'hacker'])->assertUnauthorized();
        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->postJson('/api/v1/users', [])->assertUnauthorized();
    }

    public function test_admin_routes_check_permissions(): void
    {
        Sanctum::actingAs($this->makeAdmin(), ['*'], 'sanctum-admin');

        $this->getJson('/api/v1/roles')->assertForbidden();
        $this->postJson('/api/v1/roles', ['name' => 'x'])->assertForbidden();
    }

    public function test_roles_created_by_admins_use_the_admin_guard(): void
    {
        Sanctum::actingAs($this->makeAdmin(['create-role']), ['*'], 'sanctum-admin');

        $this->postJson('/api/v1/roles', ['name' => 'editor'])->assertOk();

        $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'admin']);
    }

    public function test_super_admin_gate_bypass_applies_to_admins_only(): void
    {
        $admin = $this->makeAdmin();
        $admin->assignRole(Role::create(['name' => 'super-admin', 'guard_name' => 'admin']));
        $user = $this->makeUser();

        $this->assertTrue($admin->can('anything-at-all'));
        $this->assertFalse($user->can('anything-at-all'));
    }

    public function test_super_admin_passes_every_admin_route_without_any_assigned_permission(): void
    {
        foreach (['create-user', 'edit-user', 'delete-user', 'view-users', 'assign-role', 'create-role', 'edit-role', 'delete-role'] as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'admin']);
        }

        foreach ([0] as $i) {
            $super = $this->makeAdmin([], ['email' => "super{$i}@example.com"]);
            $super->assignRole(Role::firstOrCreate(['name' => Admin::SUPER_ADMIN_ROLE, 'guard_name' => 'admin']));
            $this->assertSame(0, $super->getAllPermissions()->count(), 'the bypass must not depend on assigned permissions');
            Sanctum::actingAs($super, ['*'], 'sanctum-admin');

            $this->getJson('/api/v1/roles')->assertOk();
            $this->postJson('/api/v1/roles', ['name' => "role-{$i}"])->assertOk();
            $this->getJson('/api/v1/users')->assertOk();
            $this->getJson('/api/v1/auth/users')->assertOk();
            $this->assertTrue($super->can('any-future-permission'));
            $this->assertTrue($super->can('delete', $this->makeUser("victim{$i}@example.com")), 'policies are bypassed too');

            $me = $this->getJson('/api/v1/admin/auth/me')->assertOk();
            $me->assertJsonPath('data.isSuperAdmin', true);
            $this->assertCount(8, $me->json('data.permissions'), 'client sees every admin permission');
        }
    }

    public function test_only_the_super_admin_role_bypasses_permissions(): void
    {
        $lookalike = $this->makeAdmin([], ['email' => 'lookalike@example.com']);
        $lookalike->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'admin']));

        $this->assertFalse($lookalike->isSuperAdmin());
        $this->assertFalse($lookalike->can('delete-user'));
    }

    public function test_regular_admin_is_not_a_super_admin(): void
    {
        $admin = $this->makeAdmin(['view-users']);
        $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'admin']));

        $this->assertFalse($admin->isSuperAdmin());
        $this->assertFalse($admin->can('delete-user'));
    }

    public function test_users_do_not_get_shields_panel_user_role(): void
    {
        // HasPanelShield skips itself in the console, so assert it is gone from the model instead.
        $this->assertNotContains(\BezhanSalleh\FilamentShield\Traits\HasPanelShield::class, class_uses_recursive(User::class));
        $this->assertFalse(config('filament-shield.panel_user.enabled'));
    }

    public function test_panel_access_is_for_admins_only(): void
    {
        $panel = Filament::getDefaultPanel();

        $this->assertSame('admin', $panel->getAuthGuard());
        $this->assertTrue($this->makeAdmin()->canAccessPanel($panel));
        $this->assertFalse($this->makeUser()->canAccessPanel($panel));
    }

    public function test_panel_login_page_uses_admin_guard(): void
    {
        $user = $this->makeUser();

        // A user signed into the web guard is still not authenticated for the panel.
        $this->actingAs($user, 'web')->get('/admin')->assertRedirect('/admin/login');
    }
}
