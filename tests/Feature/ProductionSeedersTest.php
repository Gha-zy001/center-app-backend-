<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Authorization\app\Models\Admin;
use Modules\Authorization\Database\Seeders\AdminUserSeeder;
use Modules\Authorization\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Teachers\app\Models\Teacher;
use Modules\Teachers\Database\Seeders\TeacherSeeder;
use Tests\TestCase;

class ProductionSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_is_seeded_from_the_environment_and_is_not_reset(): void
    {
        putenv('SEED_ADMIN_EMAIL=Owner@Center.test');
        putenv('SEED_ADMIN_PASSWORD=Chosen-Pass-1');
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->seed(AdminUserSeeder::class);

        $admin = Admin::where('email', 'owner@center.test')->firstOrFail();
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue(Hash::check('Chosen-Pass-1', $admin->password));

        putenv('SEED_ADMIN_PASSWORD=Another-Pass-2');
        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(Hash::check('Chosen-Pass-1', $admin->fresh()->password));
        $this->assertSame(1, Admin::count());
    }

    public function test_teacher_is_seeded_from_the_environment_and_can_log_in(): void
    {
        putenv('SEED_TEACHER_USERNAME=Mr.Ahmed');
        putenv('SEED_TEACHER_PASSWORD=Teacher-Pass-1');

        $this->seed(TeacherSeeder::class);
        $this->seed(TeacherSeeder::class);

        $this->assertSame(1, Teacher::count());
        $this->postJson('/api/v1/teacher/auth/login', ['username' => 'mr.ahmed', 'password' => 'Teacher-Pass-1'])
            ->assertOk()->assertJsonStructure(['data' => ['accessToken']]);
    }

    protected function tearDown(): void
    {
        foreach (['SEED_ADMIN_EMAIL', 'SEED_ADMIN_PASSWORD', 'SEED_TEACHER_USERNAME', 'SEED_TEACHER_PASSWORD'] as $key) {
            putenv($key);
        }

        parent::tearDown();
    }
}
