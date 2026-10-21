<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Authorization\app\Models\Admin;
use Modules\User\app\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Replays the admins migration against users that were given admin roles under the old
 * single-table model, and checks they are copied to admins and stripped on the user side.
 */
class AdminMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runAdminsMigration(): void
    {
        $migration = require base_path('Modules/Authorization/database/migrations/2026_09_30_100000_create_admins_table.php');

        Schema::dropIfExists('admins');
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_existing_admin_users_are_copied_to_admins_and_stripped(): void
    {
        $webSuper = DB::table('roles')->insertGetId(['name' => 'super-admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        $webUserRole = DB::table('roles')->insertGetId(['name' => 'user', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        $perm = Permission::create(['name' => 'view-users', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['permission_id' => $perm->id, 'role_id' => $webSuper]);

        $boss = User::create(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => Hash::make('secret-pass'), 'status' => 'active']);
        $member = User::create(['name' => 'Member', 'email' => 'member@example.com', 'password' => 'x', 'status' => 'active']);
        DB::table('model_has_roles')->insert([
            ['role_id' => $webSuper, 'model_type' => User::class, 'model_id' => $boss->id],
            ['role_id' => $webUserRole, 'model_type' => User::class, 'model_id' => $member->id],
        ]);

        // Shield used to attach panel_user to every user; it must not turn them into admins.
        $panelUser = DB::table('roles')->insertGetId(['name' => 'panel_user', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('model_has_roles')->insert(['role_id' => $panelUser, 'model_type' => User::class, 'model_id' => $member->id]);

        $this->runAdminsMigration();

        $this->assertDatabaseMissing('roles', ['name' => 'panel_user']);

        // Copied, with the same password hash.
        $admin = Admin::where('email', 'boss@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret-pass', $admin->password));
        $this->assertSame(['super-admin'], $admin->getRoleNames()->all());
        $this->assertTrue($admin->hasPermissionTo('view-users', 'admin'));

        // The user row still exists but no longer holds any admin role.
        $this->assertNotNull(User::find($boss->id));
        $this->assertSame([], $boss->fresh()->getRoleNames()->all());
        $this->assertDatabaseMissing('roles', ['name' => 'super-admin', 'guard_name' => 'web']);

        // There is exactly one super admin role.
        $this->assertSame(1, DB::table('roles')->where('guard_name', 'admin')->where('name', 'like', 'super%')->count());

        // Regular users are untouched.
        $this->assertSame(['user'], $member->fresh()->getRoleNames()->all());
        $this->assertDatabaseMissing('admins', ['email' => 'member@example.com']);
    }
}
