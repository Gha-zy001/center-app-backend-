<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Authorization\app\Models\Admin;
use Modules\User\app\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserIndexQueryTest extends TestCase
{
    use RefreshDatabase;

    private function makeUsers(int $count, Role $role, int $start = 1): void
    {
        foreach (range($start, $start + $count - 1) as $i) {
            $user = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'secret',
                'status' => 'active',
            ]);
            $user->assignRole($role);
        }
    }

    private function actingAdmin(): Admin
    {
        Permission::firstOrCreate(['name' => 'view-users', 'guard_name' => 'admin']);

        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);
        $admin->givePermissionTo('view-users');
        Sanctum::actingAs($admin, ['*'], 'sanctum-admin');

        return $admin;
    }

    private function memberRole(): Role
    {
        return Role::create(['name' => 'member', 'guard_name' => 'web']);
    }

    public function test_user_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/users')->assertUnauthorized();
    }

    public function test_a_regular_user_token_cannot_list_users(): void
    {
        $user = User::create(['name' => 'Plain', 'email' => 'plain@example.com', 'password' => 'secret', 'status' => 'active']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/users')->assertUnauthorized();
    }

    public function test_an_admin_without_view_users_permission_is_forbidden(): void
    {
        $admin = Admin::create(['name' => 'Limited', 'email' => 'limited@example.com', 'password' => 'secret']);
        Sanctum::actingAs($admin, ['*'], 'sanctum-admin');

        $this->getJson('/api/v1/auth/users')->assertForbidden();
    }

    public function test_user_index_eager_loads_roles_and_uses_flat_shape(): void
    {
        $this->actingAdmin();
        $this->makeUsers(10, $this->memberRole());

        $this->getJson('/api/v1/auth/users')->assertOk(); // warm the permission cache

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/auth/users?sort=id', ['Accept-Language' => 'en']);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'message', 'data', 'meta' => ['total', 'per_page', 'current_page', 'last_page'], 'links' => ['next']]);
        $this->assertIsArray($response->json('data.0'), 'data must be a flat list, not data.data');
        $this->assertArrayNotHasKey('data', $response->json('data'));
        $response->assertJsonPath('data.0.roles.0', 'member');

        $eagerRoleQueries = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $sql) => str_contains($sql, 'model_has_roles') && str_contains($sql, '"model_id" in'));

        $this->assertSame(1, $eagerRoleQueries->count(), 'roles must be eager loaded once for the page');
    }

    public function test_query_count_does_not_grow_with_the_number_of_users(): void
    {
        $this->actingAdmin();
        $role = $this->memberRole();

        $this->makeUsers(5, $role);
        $this->getJson('/api/v1/auth/users')->assertOk(); // warm the permission cache
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/auth/users?per_page=100')->assertOk();
        $small = count(DB::getQueryLog());

        $this->makeUsers(55, $role, 6);
        DB::flushQueryLog();
        $this->getJson('/api/v1/auth/users?per_page=100')->assertOk();
        $large = count(DB::getQueryLog());

        $this->assertSame($small, $large, "queries grew from {$small} to {$large}: N+1");
    }

    public function test_per_page_is_capped(): void
    {
        $this->actingAdmin();

        $this->getJson('/api/v1/auth/users?per_page=100000')->assertOk()->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/v1/auth/users?per_page=0')->assertOk()->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/v1/auth/users?per_page=abc')->assertOk()->assertJsonPath('meta.per_page', 10);
    }
}
