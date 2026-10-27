<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Authorization\app\Models\Admin;
use Modules\Packages\app\Models\Package;
use Modules\Packages\app\Models\TeacherSubscription;
use Modules\Teachers\app\Models\Teacher;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PackagesTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['view-packages', 'create-package', 'edit-package', 'delete-package', 'assign-package'];

    private function actAsAdmin(array $permissions = self::PERMISSIONS): Admin
    {
        $admin = Admin::create(['name' => 'Boss', 'email' => 'boss'.Admin::count().'@example.com', 'password' => 'x']);
        foreach ($permissions as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']));
        }
        Sanctum::actingAs($admin, ['*'], 'sanctum-admin');

        return $admin;
    }

    private function teacher(string $username = 'ahmed'): Teacher
    {
        return Teacher::create(['name' => ucfirst($username), 'username' => $username, 'password' => 'Str0ng!Pass1']);
    }

    private function package(array $attributes = []): Package
    {
        return Package::create($attributes + ['name' => 'Basic', 'duration_days' => 30, 'max_users' => 5, 'is_active' => true]);
    }

    public function test_admin_manages_packages_with_the_four_fields(): void
    {
        $this->actAsAdmin();

        $id = $this->postJson('/api/v1/admin/packages', ['name' => 'Basic', 'durationDays' => 30, 'maxUsers' => 5, 'isActive' => true])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Basic')
            ->assertJsonPath('data.durationDays', 30)
            ->assertJsonPath('data.maxUsers', 5)
            ->assertJsonPath('data.isActive', true)
            ->json('data.id');

        $this->postJson('/api/v1/admin/packages', ['name' => 'Basic', 'durationDays' => 90, 'maxUsers' => 20])->assertStatus(422);
        $this->postJson('/api/v1/admin/packages', ['name' => 'Bad', 'durationDays' => 0, 'maxUsers' => 20])->assertStatus(422);
        $this->postJson('/api/v1/admin/packages', ['name' => 'Pro', 'durationDays' => 90, 'maxUsers' => 20])->assertCreated();

        $this->putJson("/api/v1/admin/packages/$id", ['isActive' => false])->assertOk()
            ->assertJsonPath('data.isActive', false)->assertJsonPath('data.name', 'Basic');
        $this->getJson('/api/v1/admin/packages?isActive=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/packages?search=pr')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/packages/$id")->assertOk()->assertJsonPath('data.subscribersCount', 0);
        $this->deleteJson("/api/v1/admin/packages/$id")->assertOk();
        $this->assertDatabaseCount('packages', 1);
    }

    public function test_every_route_needs_its_permission(): void
    {
        $this->actAsAdmin(['view-packages']);
        $package = $this->package();
        $teacher = $this->teacher();

        $this->getJson('/api/v1/admin/packages')->assertOk();
        $this->postJson('/api/v1/admin/packages', ['name' => 'X', 'durationDays' => 1, 'maxUsers' => 1])->assertForbidden();
        $this->putJson("/api/v1/admin/packages/{$package->id}", ['name' => 'Y'])->assertForbidden();
        $this->deleteJson("/api/v1/admin/packages/{$package->id}")->assertForbidden();
        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/subscription", ['packageId' => $package->id])->assertForbidden();
    }

    public function test_a_super_admin_needs_no_package_permissions(): void
    {
        $admin = Admin::create(['name' => 'Root', 'email' => 'root@example.com', 'password' => 'x']);
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']));
        Sanctum::actingAs($admin, ['*'], 'sanctum-admin');
        $teacher = $this->teacher();

        // No permission was ever created or granted: the role alone is enough.
        $package = $this->postJson('/api/v1/admin/packages', ['name' => 'Basic', 'durationDays' => 30, 'maxUsers' => 5])->assertCreated()->json('data');
        $this->getJson('/api/v1/admin/packages')->assertOk();
        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/subscription", ['packageId' => $package['id']])->assertCreated();
        $this->getJson('/api/v1/admin/subscriptions')->assertOk();
        $this->deleteJson("/api/v1/admin/packages/{$package['id']}")->assertStatus(409);
    }

    public function test_admin_gives_a_teacher_a_package(): void
    {
        $this->actAsAdmin();
        $package = $this->package(['duration_days' => 90]);
        $teacher = $this->teacher();

        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/subscription", ['packageId' => $package->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.package.name', 'Basic')
            ->assertJsonPath('data.daysLeft', 90);

        $this->getJson("/api/v1/admin/teachers/{$teacher->id}/subscription")->assertOk()
            ->assertJsonPath('data.current.package.maxUsers', 5)->assertJsonCount(1, 'data.history');
        $this->getJson('/api/v1/admin/subscriptions')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher.username', 'ahmed');
        $this->getJson('/api/v1/admin/subscriptions?status=expired')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/subscriptions?expiresInDays=30')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/subscriptions?expiresInDays=100')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/packages/{$package->id}")->assertJsonPath('data.subscribersCount', 1);
    }

    public function test_an_inactive_package_cannot_be_given_and_a_used_one_cannot_be_deleted(): void
    {
        $this->actAsAdmin();
        $inactive = $this->package(['name' => 'Old', 'is_active' => false]);
        $used = $this->package(['name' => 'Used']);
        $teacher = $this->teacher();

        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/subscription", ['packageId' => $inactive->id])->assertStatus(422);
        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/subscription", ['packageId' => 999])->assertStatus(422);
        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/subscription", ['packageId' => $used->id])->assertCreated();
        $this->deleteJson("/api/v1/admin/packages/{$used->id}")->assertStatus(409);
        $this->assertDatabaseHas('packages', ['id' => $used->id]);
    }

    public function test_extending_keeps_the_days_left_and_replacing_starts_now(): void
    {
        $this->actAsAdmin();
        $package = $this->package();
        $teacher = $this->teacher();
        $url = "/api/v1/admin/teachers/{$teacher->id}/subscription";

        $this->postJson($url, ['packageId' => $package->id])->assertCreated();
        $this->travel(10)->days();

        $this->postJson($url, ['packageId' => $package->id, 'extend' => true])->assertCreated()->assertJsonPath('data.daysLeft', 50);
        $this->assertDatabaseCount('teacher_subscriptions', 2);

        $this->postJson($url, ['packageId' => $package->id])->assertCreated()->assertJsonPath('data.daysLeft', 30);
        // The replaced periods were closed, so only the last one is still running.
        $this->assertSame(1, TeacherSubscription::where('ends_at', '>', now())->count());
    }

    public function test_an_expired_package_locks_the_teacher_out_at_once(): void
    {
        $this->actAsAdmin();
        $package = $this->package();
        $teacher = $this->teacher();
        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/subscription", ['packageId' => $package->id])->assertCreated();

        $login = fn () => $this->postJson('/api/v1/teacher/auth/login', ['username' => 'ahmed', 'password' => 'Str0ng!Pass1']);
        $token = $login()->assertOk()->json('data.accessToken');
        $this->withToken($token)->getJson('/api/v1/teacher/subscription')->assertOk()
            ->assertJsonPath('data.subscription.package.name', 'Basic');

        $this->travel(31)->days();

        $this->withToken($token)->getJson('/api/v1/teacher/groups')->assertForbidden();
        $login()->assertForbidden();
    }

    public function test_the_hourly_job_suspends_the_account_and_a_renewal_brings_it_back(): void
    {
        $this->actAsAdmin();
        $package = $this->package();
        $teacher = $this->teacher();
        $url = "/api/v1/admin/teachers/{$teacher->id}/subscription";
        $this->postJson($url, ['packageId' => $package->id])->assertCreated();
        $teacher->createToken('t');

        $this->artisan('subscriptions:expire')->assertSuccessful();
        $this->assertSame('active', $teacher->fresh()->status); // still running: nothing to do

        $this->travel(31)->days();
        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame('suspended', $teacher->fresh()->status);
        $this->assertSame(0, $teacher->tokens()->count());

        $this->postJson($url, ['packageId' => $package->id])->assertCreated();
        $this->assertSame('active', $teacher->fresh()->status);
        $this->postJson('/api/v1/teacher/auth/login', ['username' => 'ahmed', 'password' => 'Str0ng!Pass1'])->assertOk();
    }

    public function test_a_manual_suspension_is_not_lifted_by_a_renewal(): void
    {
        $this->actAsAdmin();
        $package = $this->package();
        $teacher = $this->teacher();
        $url = "/api/v1/admin/teachers/{$teacher->id}/subscription";
        $this->postJson($url, ['packageId' => $package->id])->assertCreated();

        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/deactivate")->assertStatus(403); // no teacher permission here
        $teacher->update(['status' => 'suspended']);
        $this->postJson($url, ['packageId' => $package->id])->assertCreated();

        $this->assertSame('suspended', $teacher->fresh()->status);
    }

    public function test_teachers_without_a_package_keep_working(): void
    {
        $this->teacher();

        $token = $this->postJson('/api/v1/teacher/auth/login', ['username' => 'ahmed', 'password' => 'Str0ng!Pass1'])->assertOk()->json('data.accessToken');
        $this->withToken($token)->getJson('/api/v1/teacher/groups')->assertOk();
        $this->withToken($token)->getJson('/api/v1/teacher/subscription')->assertOk()->assertJsonPath('data.subscription', null);
        $this->artisan('subscriptions:expire')->assertSuccessful();
    }

    public function test_package_endpoints_need_authentication(): void
    {
        $this->getJson('/api/v1/admin/packages')->assertUnauthorized();
        $this->getJson('/api/v1/teacher/subscription')->assertUnauthorized();
    }
}
