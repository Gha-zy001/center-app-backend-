<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Teachers\app\Models\Teacher;
use Tests\TestCase;

class ClassroomTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/teacher';

    private function actAs(string $username = 'ahmed'): Teacher
    {
        $teacher = Teacher::firstOrCreate(['username' => $username], ['name' => ucfirst($username), 'password' => 'Str0ng!Pass1']);
        Sanctum::actingAs($teacher, ['*'], 'sanctum-teacher');

        return $teacher;
    }

    private function makeGroup(string $name = 'Math A'): array
    {
        return $this->postJson(self::BASE.'/groups', ['name' => $name, 'days' => ['sat', 'mon'], 'time' => '17:30'])
            ->assertCreated()->json('data.group');
    }

    private function makeStudent(string $groupId, string $name = 'Omar'): array
    {
        return $this->postJson(self::BASE.'/students', [
            'name' => $name, 'phone' => '', 'parentPhone' => '+201000000001', 'groupId' => (int) $groupId,
        ])->assertCreated()->json('data.student');
    }

    public function test_requires_a_teacher_token(): void
    {
        $this->getJson(self::BASE.'/groups')->assertUnauthorized();
    }

    public function test_group_crud_and_unique_name(): void
    {
        $this->actAs();
        $group = $this->makeGroup();

        $this->assertSame(0, $group['studentCount']);
        $this->postJson(self::BASE.'/groups', ['name' => 'math a', 'days' => ['sun'], 'time' => '10:00'])
            ->assertStatus(409)->assertJsonPath('code', 'group_name_taken');
        $this->putJson(self::BASE."/groups/{$group['id']}", ['name' => 'Math B', 'days' => ['sun'], 'time' => '10:00'])
            ->assertOk()->assertJsonPath('data.group.name', 'Math B');

        $this->makeStudent($group['id']);
        $this->deleteJson(self::BASE."/groups/{$group['id']}")->assertStatus(409)->assertJsonPath('code', 'group_has_students');
        $this->getJson(self::BASE.'/groups')->assertJsonPath('data.items.0.studentCount', 1);
    }

    public function test_student_needs_one_phone_and_supports_search(): void
    {
        $this->actAs();
        $group = $this->makeGroup();

        $this->postJson(self::BASE.'/students', ['name' => 'X', 'phone' => '', 'parentPhone' => '', 'groupId' => (int) $group['id']])
            ->assertStatus(422);

        $student = $this->makeStudent($group['id'], 'Omar Ali');
        $this->assertNotEmpty($student['id']);
        $this->getJson(self::BASE.'/students?search=omar')->assertJsonPath('data.total', 1);
        $this->getJson(self::BASE.'/students?search=zzz')->assertJsonPath('data.total', 0);
        $this->getJson(self::BASE."/students/{$student['id']}")->assertJsonPath('data.student.telegramLink', null)
            ->assertJsonStructure(['data' => ['student' => ['qrCode']]]);
    }

    public function test_another_teachers_rows_are_404(): void
    {
        $this->actAs('first');
        $group = $this->makeGroup();
        $student = $this->makeStudent($group['id']);

        $this->actAs('second');
        $this->getJson(self::BASE."/students/{$student['id']}")->assertNotFound();
        $this->putJson(self::BASE."/groups/{$group['id']}", ['name' => 'Hack', 'days' => ['sun'], 'time' => '10:00'])->assertNotFound();
        $this->getJson(self::BASE.'/groups')->assertJsonPath('data.items', []);
    }

    public function test_attendance_scan_and_close(): void
    {
        $this->actAs();
        $group = $this->makeGroup();
        $present = $this->makeStudent($group['id'], 'Present');
        $absent = $this->makeStudent($group['id'], 'Absent');
        $qr = $this->getJson(self::BASE."/students/{$present['id']}")->json('data.student.qrCode');
        $today = now()->toDateString();
        $body = ['groupId' => (int) $group['id'], 'date' => $today];

        $this->postJson(self::BASE.'/attendance/scan', $body + ['qrCode' => $qr])->assertOk()->assertJsonPath('data.alreadyRecorded', false);
        $this->postJson(self::BASE.'/attendance/scan', $body + ['qrCode' => $qr])->assertJsonPath('data.alreadyRecorded', true);
        $this->postJson(self::BASE.'/attendance/scan', $body + ['qrCode' => 'nope'])->assertNotFound()->assertJsonPath('code', 'student_not_found');

        $this->postJson(self::BASE.'/attendance/close', $body)->assertJsonPath('data.absentCount', 1);
        $this->postJson(self::BASE.'/attendance/close', $body)->assertJsonPath('data.absentCount', 0);

        $session = $this->getJson(self::BASE."/attendance/session?groupId={$group['id']}&date=$today")->assertOk();
        $session->assertJsonPath('data.closed', true);
        $this->assertEqualsCanonicalizing(['present', 'absent'], array_column($session->json('data.students'), 'status'));

        $this->getJson(self::BASE."/students/{$absent['id']}/attendance")->assertJsonPath('data.absentCount', 1);
    }

    public function test_exams_and_grades(): void
    {
        $this->actAs();
        $group = $this->makeGroup();
        $student = $this->makeStudent($group['id']);

        $exam = $this->postJson(self::BASE.'/exams', ['groupId' => (int) $group['id'], 'name' => 'Quiz 1', 'maxScore' => 20])
            ->assertCreated()->json('data.exam');

        $url = self::BASE."/exams/{$exam['id']}/grades/{$student['id']}";
        $this->putJson($url, ['score' => 25])->assertStatus(400)->assertJsonPath('code', 'score_out_of_range');
        $this->putJson($url, ['score' => 17.5])->assertOk()->assertJsonPath('data.score', 17.5);
        $this->getJson(self::BASE."/exams/{$exam['id']}/grades")->assertJsonPath('data.items.0.score', 17.5);
        $this->getJson(self::BASE."/students/{$student['id']}/grades")->assertJsonPath('data.items.0.score', 17.5);

        $this->putJson($url, ['score' => null])->assertOk();
        $this->getJson(self::BASE."/exams/{$exam['id']}/grades")->assertJsonPath('data.items.0.score', null);
        $this->deleteJson(self::BASE."/exams/{$exam['id']}")->assertOk();
    }
}
