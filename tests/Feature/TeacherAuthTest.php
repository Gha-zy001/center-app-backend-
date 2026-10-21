<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Modules\Authorization\app\Models\Admin;
use Modules\Teachers\app\Concerns\BelongsToTeacher;
use Modules\Teachers\app\Models\Teacher;
use Modules\Teachers\app\Support\TeacherContext;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TeacherAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Pass1';

    private function makeTeacher(array $overrides = []): Teacher
    {
        return Teacher::create(array_merge([
            'name' => 'Mr Ahmed',
            'username' => 'ahmed',
            'password' => self::PASSWORD,
        ], $overrides));
    }

    private function makeAdmin(array $permissions = []): Admin
    {
        $admin = Admin::create([
            'name' => 'Boss',
            'email' => 'boss@example.com',
            'password' => Hash::make(self::PASSWORD),
        ]);

        foreach ($permissions as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']));
        }

        return $admin;
    }

    public function test_teacher_logs_in_with_username_and_password(): void
    {
        $teacher = $this->makeTeacher();

        $response = $this->postJson('/api/v1/teacher/auth/login', ['username' => 'Ahmed', 'password' => self::PASSWORD]);

        $response->assertOk()->assertJsonPath('data.teacher.username', 'ahmed');
        $this->assertNotNull($teacher->fresh()->last_login_at);

        $this->withToken($response->json('data.accessToken'))
            ->getJson('/api/v1/teacher/auth/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'ahmed');
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $this->makeTeacher();

        $this->postJson('/api/v1/teacher/auth/login', ['username' => 'ahmed', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/v1/teacher/auth/login', ['username' => 'nobody', 'password' => self::PASSWORD])->assertStatus(422);
    }

    public function test_suspended_teacher_cannot_log_in_and_existing_tokens_stop_working(): void
    {
        $teacher = $this->makeTeacher();
        $token = $teacher->createToken('t')->plainTextToken;

        $teacher->update(['status' => Teacher::STATUS_SUSPENDED]);

        $this->postJson('/api/v1/teacher/auth/login', ['username' => 'ahmed', 'password' => self::PASSWORD])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/teacher/auth/me')->assertForbidden();
    }

    public function test_suspended_teacher_with_wrong_password_does_not_learn_the_status(): void
    {
        $this->makeTeacher(['status' => Teacher::STATUS_SUSPENDED]);

        $this->postJson('/api/v1/teacher/auth/login', ['username' => 'ahmed', 'password' => 'wrong'])->assertStatus(422);
    }

    public function test_logout_revokes_the_token(): void
    {
        $this->makeTeacher();
        $token = $this->postJson('/api/v1/teacher/auth/login', ['username' => 'ahmed', 'password' => self::PASSWORD])
            ->json('data.accessToken');

        $this->withToken($token)->postJson('/api/v1/teacher/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/teacher/auth/me')->assertUnauthorized();
    }

    public function test_teacher_admin_and_user_tokens_are_not_interchangeable(): void
    {
        $teacherToken = $this->makeTeacher()->createToken('t')->plainTextToken;
        $adminToken = $this->makeAdmin()->createToken('t')->plainTextToken;

        $this->withToken($teacherToken)->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
        $this->withToken($teacherToken)->getJson('/api/v1/admin/teachers')->assertUnauthorized();
        $this->withToken($teacherToken)->getJson('/api/v1/auth/profile')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($adminToken)->getJson('/api/v1/teacher/auth/me')->assertUnauthorized();
    }

    public function test_teacher_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/teacher/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/teacher/auth/logout')->assertUnauthorized();
    }

    public function test_admin_teacher_routes_require_authentication_and_permission(): void
    {
        $this->getJson('/api/v1/admin/teachers')->assertUnauthorized();
        $this->postJson('/api/v1/admin/teachers', [])->assertUnauthorized();

        Sanctum::actingAs($this->makeAdmin(), ['*'], 'sanctum-admin');

        $this->getJson('/api/v1/admin/teachers')->assertForbidden();
        $this->postJson('/api/v1/admin/teachers', ['name' => 'x', 'username' => 'xxx'])->assertForbidden();
    }

    public function test_admin_creates_a_teacher_with_a_generated_password(): void
    {
        Sanctum::actingAs($this->makeAdmin(['create-teacher']), ['*'], 'sanctum-admin');

        $response = $this->postJson('/api/v1/admin/teachers', ['name' => 'Mrs Sara', 'username' => 'Sara.M', 'phone' => '01000000000']);

        $response->assertCreated()->assertJsonPath('data.teacher.username', 'sara.m');
        $temporary = $response->json('data.temporaryPassword');
        $this->assertNotEmpty($temporary);

        $this->postJson('/api/v1/teacher/auth/login', ['username' => 'sara.m', 'password' => $temporary])->assertOk();
    }

    public function test_admin_cannot_create_a_duplicate_username(): void
    {
        $this->makeTeacher();
        Sanctum::actingAs($this->makeAdmin(['create-teacher']), ['*'], 'sanctum-admin');

        $this->postJson('/api/v1/admin/teachers', ['name' => 'Other', 'username' => 'ahmed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_admin_suspends_and_reactivates_a_teacher(): void
    {
        $teacher = $this->makeTeacher();
        $teacher->createToken('t');
        Sanctum::actingAs($this->makeAdmin(['change-teacher-status']), ['*'], 'sanctum-admin');

        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');
        $this->assertSame(0, $teacher->tokens()->count());
        $this->postJson('/api/v1/teacher/auth/login', ['username' => 'ahmed', 'password' => self::PASSWORD])->assertForbidden();

        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/activate")->assertOk()->assertJsonPath('data.isActive', true);
        $this->postJson('/api/v1/teacher/auth/login', ['username' => 'ahmed', 'password' => self::PASSWORD])->assertOk();
    }

    public function test_admin_password_reset_revokes_tokens_and_applies(): void
    {
        $teacher = $this->makeTeacher();
        $teacher->createToken('t');
        Sanctum::actingAs($this->makeAdmin(['edit-teacher']), ['*'], 'sanctum-admin');

        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/password", ['password' => 'weak'])->assertStatus(422);
        $this->postJson("/api/v1/admin/teachers/{$teacher->id}/password", ['password' => 'N3wStrongPass'])->assertOk();

        $this->assertSame(0, $teacher->tokens()->count());
        $this->postJson('/api/v1/teacher/auth/login', ['username' => 'ahmed', 'password' => 'N3wStrongPass'])->assertOk();
    }

    public function test_admin_lists_updates_and_deletes_teachers(): void
    {
        $teacher = $this->makeTeacher();
        $this->makeTeacher(['name' => 'Other', 'username' => 'other']);
        Sanctum::actingAs($this->makeAdmin(['view-teachers', 'edit-teacher', 'delete-teacher']), ['*'], 'sanctum-admin');

        $this->getJson('/api/v1/admin/teachers')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/admin/teachers?search=oth')->assertOk()->assertJsonPath('meta.total', 1);

        $this->postJson("/api/v1/admin/teachers/{$teacher->id}", ['username' => 'other'])->assertStatus(422);
        $this->postJson("/api/v1/admin/teachers/{$teacher->id}", ['name' => 'Renamed'])->assertOk();
        $this->assertSame('Renamed', $teacher->fresh()->name);

        $this->deleteJson("/api/v1/admin/teachers/{$teacher->id}")->assertOk();
        $this->assertSoftDeleted('teachers', ['id' => $teacher->id]);
    }

    public function test_teacher_scope_isolates_rows_between_teachers_and_fails_closed(): void
    {
        Schema::create('scope_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id');
            $table->string('body');
        });

        $note = new class extends Model
        {
            use BelongsToTeacher;

            protected $table = 'scope_notes';

            public $timestamps = false;

            protected $guarded = [];
        };
        $model = $note::class;

        $a = $this->makeTeacher();
        $b = $this->makeTeacher(['username' => 'bravo']);
        $context = app(TeacherContext::class);

        $context->runFor($a, fn () => $model::create(['body' => 'a-note']));
        $context->runFor($b, fn () => $model::create(['body' => 'b-note']));

        $this->assertSame(['a-note'], $context->runFor($a, fn () => $model::pluck('body')->all()));
        $this->assertSame(['b-note'], $context->runFor($b, fn () => $model::pluck('body')->all()));

        // No teacher context: nothing is readable, and nothing can be created.
        $this->assertSame(0, $model::count());
        $this->expectException(LogicException::class);
        $model::create(['body' => 'orphan']);
    }

    public function test_teacher_context_is_cleared_after_each_request(): void
    {
        $token = $this->makeTeacher()->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/teacher/auth/me')->assertOk();

        $this->assertFalse(app(TeacherContext::class)->isSet());
    }
}
