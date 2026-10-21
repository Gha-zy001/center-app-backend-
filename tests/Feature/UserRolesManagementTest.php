<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Authorization\app\Models\Admin;
use Modules\User\app\Filament\Resources\UserPermissionResource\Pages\ManageUserPermissions;
use Modules\User\app\Filament\Resources\UserRoleResource\Pages\ManageUserRoles;
use Modules\User\app\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRolesManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Pass1';

    private function manager(array $extra = []): Admin
    {
        $admin = Admin::create(['name' => 'Manager', 'email' => 'manager@example.com', 'password' => 'x']);
        foreach (['manage-user-roles', ...$extra] as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']));
        }

        return $admin;
    }

    private function actingManager(array $extra = []): Admin
    {
        $admin = $this->manager($extra);
        Sanctum::actingAs($admin, ['*'], 'sanctum-admin');

        return $admin;
    }

    private function webPermission(string $name): Permission
    {
        return Permission::create(['name' => $name, 'guard_name' => 'web']);
    }

    // ----------------------------------------------------------------- API

    public function test_requires_the_manage_user_roles_permission(): void
    {
        $this->getJson('/api/v1/user-roles')->assertUnauthorized();

        $noPerm = Admin::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'x']);
        Sanctum::actingAs($noPerm, ['*'], 'sanctum-admin');
        $this->getJson('/api/v1/user-roles')->assertForbidden();
        $this->postJson('/api/v1/user-permissions', ['name' => 'a'])->assertForbidden();

    }

    public function test_user_tokens_are_rejected(): void
    {
        $user = User::create(['name' => 'U', 'email' => 'u@example.com', 'password' => 'x', 'status' => 'active']);

        $this->withToken($user->createToken('t')->plainTextToken)->getJson('/api/v1/user-roles')->assertUnauthorized();
    }

    public function test_admin_can_build_a_user_role_with_permissions(): void
    {
        $this->actingManager();

        $this->postJson('/api/v1/user-permissions', ['name' => 'manage-products'])->assertCreated();
        $this->postJson('/api/v1/user-permissions', ['name' => 'export-reports'])->assertCreated();
        $this->getJson('/api/v1/user-permissions')->assertOk()->assertJsonPath('data', ['export-reports', 'manage-products']);

        $this->postJson('/api/v1/user-roles', ['name' => 'vendor', 'permissions' => ['manage-products']])
            ->assertCreated()
            ->assertJsonPath('data.name', 'vendor')
            ->assertJsonPath('data.permissions', ['manage-products']);

        $this->assertDatabaseHas('roles', ['name' => 'vendor', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'manage-products', 'guard_name' => 'web']);

        $role = Role::findByName('vendor', 'web');
        $this->postJson("/api/v1/user-roles/{$role->id}", ['permissions' => ['manage-products', 'export-reports']])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['export-reports', 'manage-products']);

        $this->postJson("/api/v1/user-roles/{$role->id}", ['name' => 'seller'])->assertOk()->assertJsonPath('data.name', 'seller');
        $this->deleteJson("/api/v1/user-roles/{$role->id}")->assertOk();
        $this->assertDatabaseMissing('roles', ['name' => 'seller']);
    }

    public function test_validation_rejects_bad_names_duplicates_and_admin_permissions(): void
    {
        $this->actingManager();
        Role::create(['name' => 'vendor', 'guard_name' => 'web']);
        Permission::create(['name' => 'view-users', 'guard_name' => 'admin']);

        $this->postJson('/api/v1/user-roles', ['name' => 'Vendor Role!'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/user-roles', ['name' => 'vendor'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/user-roles', ['name' => 'spy', 'permissions' => ['view-users']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissions.0');
    }

    public function test_a_user_role_may_share_a_name_with_an_admin_role(): void
    {
        $this->actingManager();
        Role::create(['name' => 'editor', 'guard_name' => 'admin']);

        $this->postJson('/api/v1/user-roles', ['name' => 'editor'])->assertCreated();
        $this->assertSame(2, Role::where('name', 'editor')->count());
    }

    public function test_admin_roles_and_permissions_cannot_be_touched_through_user_endpoints(): void
    {
        $this->actingManager();
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'admin']);
        $adminPermission = Permission::firstOrCreate(['name' => 'view-users', 'guard_name' => 'admin']);

        $this->postJson("/api/v1/user-roles/{$adminRole->id}", ['name' => 'hacked'])->assertNotFound();
        $this->deleteJson("/api/v1/user-roles/{$adminRole->id}")->assertNotFound();
        $this->deleteJson("/api/v1/user-permissions/{$adminPermission->id}")->assertNotFound();

        $this->assertDatabaseHas('roles', ['name' => 'admin', 'guard_name' => 'admin']);
        $this->assertDatabaseHas('permissions', ['name' => 'view-users', 'guard_name' => 'admin']);
        $this->getJson('/api/v1/user-roles')->assertJsonMissing(['name' => 'admin']);
    }

    public function test_default_user_role_cannot_be_renamed_or_deleted_but_can_get_permissions(): void
    {
        $this->actingManager();
        $default = Role::create(['name' => 'user', 'guard_name' => 'web']);
        $this->webPermission('post-comments');

        $this->postJson("/api/v1/user-roles/{$default->id}", ['name' => 'member'])->assertStatus(422);
        $this->deleteJson("/api/v1/user-roles/{$default->id}")->assertStatus(422);
        $this->postJson("/api/v1/user-roles/{$default->id}", ['permissions' => ['post-comments']])
            ->assertOk()
            ->assertJsonPath('data.isDefault', true)
            ->assertJsonPath('data.permissions', ['post-comments']);
    }

    public function test_admin_can_assign_roles_and_direct_permissions_to_a_user(): void
    {
        $this->actingManager(['create-user', 'edit-user']);
        $vendor = Role::create(['name' => 'vendor', 'guard_name' => 'web']);
        $vendor->givePermissionTo($this->webPermission('manage-products'));
        $this->webPermission('export-reports');

        $created = $this->postJson('/api/v1/users', [
            'name' => 'Seller',
            'email' => 'seller@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'roles' => ['vendor'],
            'permissions' => ['export-reports'],
        ])->assertOk();

        $created->assertJsonPath('data.roles', ['vendor']);
        $this->assertEqualsCanonicalizing(['manage-products', 'export-reports'], $created->json('data.permissions'));

        $user = User::where('email', 'seller@example.com')->firstOrFail();
        $this->postJson("/api/v1/users/{$user->uuid}", ['permissions' => []])->assertOk();
        $this->assertSame(['manage-products'], $user->fresh()->getAllPermissions()->pluck('name')->all());
    }

    public function test_role_permission_changes_apply_to_users_immediately(): void
    {
        $this->actingManager();
        $role = Role::create(['name' => 'vendor', 'guard_name' => 'web']);
        $this->webPermission('manage-products');

        $user = User::create(['name' => 'U', 'email' => 'u@example.com', 'password' => Hash::make(self::PASSWORD), 'status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole($role);
        $this->assertFalse($user->fresh()->can('manage-products'));

        $this->postJson("/api/v1/user-roles/{$role->id}", ['permissions' => ['manage-products']])->assertOk();

        $this->assertTrue($user->fresh()->can('manage-products'), 'the permission cache must not hide the new permission');

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'u@example.com', 'password' => self::PASSWORD]);
        $login->assertJsonPath('data.user.permissions', ['manage-products']);
    }

    // -------------------------------------------------------------- Panel

    public function test_panel_screens_require_the_permission(): void
    {
        $noPerm = Admin::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'x']);
        $this->actingAs($noPerm, 'admin');

        $this->get('/admin/user-roles')->assertForbidden();
        $this->get('/admin/user-permissions')->assertForbidden();

        $html = $this->get('/admin')->getContent();
        $this->assertStringNotContainsString('/admin/user-roles', $html, 'hidden from navigation too');
    }

    public function test_panel_screens_open_with_the_permission(): void
    {
        $this->actingAs($this->manager(), 'admin');
        $this->get('/admin/user-roles')->assertOk();
        $this->get('/admin/user-permissions')->assertOk();
    }

    public function test_panel_creates_edits_and_protects_user_roles(): void
    {
        $this->actingAs($this->manager(), 'admin');
        $perm = $this->webPermission('manage-products');
        $default = Role::create(['name' => 'user', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'admin']);

        Livewire::test(ManageUserRoles::class)
            ->assertCanSeeTableRecords([$default])
            ->assertCanNotSeeTableRecords([$adminRole])
            ->callAction('create', data: ['name' => 'vendor', 'permissions' => [$perm->id]])
            ->assertHasNoActionErrors();

        $vendor = Role::findByName('vendor', 'web');
        $this->assertSame(['manage-products'], $vendor->permissions->pluck('name')->all());

        Livewire::test(ManageUserRoles::class)
            ->assertTableActionVisible('delete', $vendor)
            ->assertTableActionHidden('delete', $default);

        Livewire::test(ManageUserRoles::class)
            ->callTableAction('edit', $vendor, data: ['name' => 'seller', 'permissions' => []])
            ->assertHasNoTableActionErrors();

        $this->assertSame('seller', $vendor->fresh()->name);
        $this->assertCount(0, $vendor->fresh()->permissions);
    }

    public function test_panel_manages_the_user_permission_catalogue(): void
    {
        $this->actingAs($this->manager(), 'admin');
        $adminPermission = Permission::firstOrCreate(['name' => 'view-users', 'guard_name' => 'admin']);

        Livewire::test(ManageUserPermissions::class)
            ->assertCanNotSeeTableRecords([$adminPermission])
            ->callAction('create', data: ['name' => 'export-reports'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('permissions', ['name' => 'export-reports', 'guard_name' => 'web']);

        Livewire::test(ManageUserPermissions::class)
            ->callAction('create', data: ['name' => 'Bad Name'])
            ->assertHasActionErrors(['name']);
    }
}
