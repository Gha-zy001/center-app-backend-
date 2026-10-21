<?php

namespace Tests\Feature;

use App\Policies\ActivityPolicy;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Authorization\app\Models\Admin;
use Modules\Authorization\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Blog\app\Policies\BlogCategoryPolicy;
use Modules\Blog\app\Policies\BlogPolicy;
use Modules\User\app\Models\User;
use Modules\User\app\Policies\UserPolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function adminWith(array $permissions, string $email = 'staff@example.com'): Admin
    {
        $admin = Admin::create(['name' => 'Staff', 'email' => $email, 'password' => 'x']);
        foreach ($permissions as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']));
        }

        return $admin;
    }

    /**
     * Every permission referenced by a policy, an API route or a panel page must be created by
     * the seeder. This is the check that would have caught the Shield-style names before.
     */
    public function test_every_permission_the_code_checks_is_seeded(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $seeded = Permission::where('guard_name', 'admin')->pluck('name')->all();

        $required = [];

        foreach ([UserPolicy::class, BlogPolicy::class, BlogCategoryPolicy::class, RolePolicy::class, ActivityPolicy::class] as $policy) {
            $required = array_merge($required, array_values((new \ReflectionClassConstant($policy, 'PERMISSIONS'))->getValue()));
        }

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            foreach ((array) $route->middleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                    $required[] = Str::after($middleware, 'can:');
                }
            }
        }

        $required[] = 'manage-settings';
        $required[] = 'send-notifications';

        $missing = array_diff(array_unique($required), $seeded);
        $this->assertSame([], array_values($missing), 'permissions checked in code but never seeded');
    }

    public function test_seeded_admin_role_can_do_everything_it_is_given_but_is_not_super(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = Admin::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'x']);
        $admin->assignRole('admin');

        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('manage-settings'));
    }

    public function test_policies_follow_the_permission_names(): void
    {
        $viewer = $this->adminWith(['view-users']);
        $target = User::create(['name' => 'U', 'email' => 'u@example.com', 'password' => 'x', 'status' => 'active']);

        $this->assertTrue($viewer->can('viewAny', User::class));
        $this->assertTrue($viewer->can('view', $target));
        $this->assertFalse($viewer->can('update', $target));
        $this->assertFalse($viewer->can('delete', $target));
        $this->assertFalse($viewer->can('create', User::class));

        $editor = $this->adminWith(['view-users', 'edit-user'], 'editor@example.com');
        $this->assertTrue($editor->can('update', $target));
        $this->assertFalse($editor->can('forceDelete', $target));
    }

    public function test_activity_log_cannot_be_created_or_edited_even_with_every_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = Admin::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'x']);
        $admin->assignRole('admin');

        $policy = new ActivityPolicy;
        $this->assertTrue($policy->viewAny($admin));
        $this->assertFalse($policy->create($admin));
        $this->assertFalse($policy->update($admin));
    }

    public function test_admin_without_permissions_cannot_open_settings_or_send_notification(): void
    {
        $this->actingAs($this->adminWith([]), 'admin');

        $this->get('/admin/manage-settings')->assertForbidden();
        $this->get('/admin/send-notification')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin')->assertOk();
    }

    public function test_pages_open_with_their_permission(): void
    {
        $this->actingAs($this->adminWith(['manage-settings', 'send-notifications', 'view-users']), 'admin');

        $this->get('/admin/manage-settings')->assertOk();
        $this->get('/admin/send-notification')->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/blogs')->assertForbidden();
    }

    public function test_navigation_only_shows_what_the_admin_may_open(): void
    {
        $this->actingAs($this->adminWith(['view-users']), 'admin');

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('/admin/users', $html);
        $this->assertStringNotContainsString('/admin/manage-settings', $html);
        $this->assertStringNotContainsString('/admin/send-notification', $html);
    }

    public function test_navigation_hides_users_without_view_permission(): void
    {
        $this->actingAs($this->adminWith(['manage-settings']), 'admin');

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('/admin/manage-settings', $html);
        $this->assertStringNotContainsString('/admin/users', $html);
    }

    public function test_super_admin_still_opens_everything(): void
    {
        $admin = $this->adminWith([]);
        $admin->assignRole(Role::create(['name' => Admin::SUPER_ADMIN_ROLE, 'guard_name' => 'admin']));
        $this->actingAs($admin, 'admin');

        $this->get('/admin/manage-settings')->assertOk();
        $this->get('/admin/send-notification')->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/blogs')->assertOk();
    }
}
