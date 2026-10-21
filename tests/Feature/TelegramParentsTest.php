<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Authorization\app\Models\Admin;
use Modules\Teachers\app\Models\Teacher;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TelegramParentsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/teacher';

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.token' => 'TEST:TOKEN',
            'services.telegram.bot_username' => 'followup_bot',
            'services.telegram.webhook_secret' => self::SECRET,
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);
    }

    private function actAs(string $username = 'ahmed'): void
    {
        $teacher = Teacher::firstOrCreate(['username' => $username], ['name' => ucfirst($username), 'password' => 'Str0ng!Pass1']);
        Sanctum::actingAs($teacher, ['*'], 'sanctum-teacher');
    }

    private function makeStudent(string $parentPhone = '01001234567', string $name = 'Omar'): array
    {
        $group = $this->postJson(self::BASE.'/groups', ['name' => 'Math A', 'days' => ['sat', 'mon'], 'time' => '17:30'])->json('data.group');

        return $this->postJson(self::BASE.'/students', [
            'name' => $name, 'phone' => '', 'parentPhone' => $parentPhone, 'groupId' => (int) $group['id'],
        ])->assertCreated()->json('data.student') + ['group' => $group];
    }

    private function telegram(array $message): void
    {
        $this->postJson('/api/v1/telegram/webhook', ['update_id' => 1, 'message' => $message + ['chat' => ['id' => 555, 'type' => 'private']]], [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])->assertOk();
    }

    private function shareContact(string $phone, int $userId = 555): void
    {
        $this->telegram(['from' => ['id' => 555, 'first_name' => 'Ali'], 'contact' => ['phone_number' => $phone, 'user_id' => $userId]]);
    }

    private function sentTexts(): array
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'sendMessage'))
            ->map(fn ($pair) => $pair[0]['text'])->values()->all();
    }

    public function test_webhook_rejects_requests_without_the_secret(): void
    {
        $this->postJson('/api/v1/telegram/webhook', ['message' => []])->assertForbidden();
        $this->postJson('/api/v1/telegram/webhook', [], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])->assertForbidden();
    }

    public function test_start_asks_the_parent_to_share_their_number(): void
    {
        $this->telegram(['from' => ['id' => 555, 'first_name' => 'Ali'], 'text' => '/start']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && $request['reply_markup']['keyboard'][0][0]['request_contact'] === true);
    }

    public function test_sharing_the_parent_number_links_every_matching_student(): void
    {
        $this->actAs();
        $student = $this->makeStudent('+20 100 123 4567');
        $this->postJson(self::BASE.'/students', ['name' => 'Sara', 'phone' => '', 'parentPhone' => '01001234567', 'groupId' => (int) $student['groupId']]);

        $this->shareContact('201001234567');

        $details = $this->getJson(self::BASE."/students/{$student['id']}")->assertOk();
        $details->assertJsonPath('data.student.telegramLink', 'https://t.me/followup_bot');
        $details->assertJsonCount(1, 'data.student.telegramContacts');
        $this->assertStringContainsString('Sara', implode("\n", $this->sentTexts()));
    }

    public function test_an_unknown_number_or_someone_elses_contact_links_nothing(): void
    {
        $this->actAs();
        $student = $this->makeStudent();

        $this->shareContact('201111111111');
        $this->shareContact('201001234567', userId: 999); // forwarded contact, not the sender's own

        $this->getJson(self::BASE."/students/{$student['id']}")->assertJsonCount(0, 'data.student.telegramContacts');
    }

    public function test_absence_grade_and_schedule_reach_the_linked_parent(): void
    {
        $this->actAs();
        $student = $this->makeStudent();
        $this->shareContact('201001234567');
        $already = count($this->sentTexts()); // the replies sent while linking

        $today = now()->toDateString();
        $body = ['groupId' => (int) $student['groupId'], 'date' => $today];

        $this->postJson(self::BASE.'/attendance/close', $body)
            ->assertJsonPath('data.parentsNotified', 1)->assertJsonPath('data.parentsNotLinked', 0);

        $exam = $this->postJson(self::BASE.'/exams', ['groupId' => (int) $student['groupId'], 'name' => 'Quiz 1', 'maxScore' => 20])->json('data.exam');
        $this->putJson(self::BASE."/exams/{$exam['id']}/grades/{$student['id']}", ['score' => 17.5])->assertOk();
        $this->putJson(self::BASE."/exams/{$exam['id']}/grades/{$student['id']}", ['score' => 17.5])->assertOk(); // same score: no second message

        $this->putJson(self::BASE."/groups/{$student['groupId']}", ['name' => 'Math A', 'days' => ['sun'], 'time' => '10:00'])->assertOk();

        $sent = array_slice($this->sentTexts(), $already);
        $texts = implode("\n", $sent);
        $this->assertStringContainsString('غاب', $texts);
        $this->assertStringContainsString('17.5', $texts);
        $this->assertStringContainsString('الأحد', $texts);
        $this->assertCount(3, $sent);
    }

    public function test_scan_tells_the_parent_once_and_reports_it(): void
    {
        $this->actAs();
        $student = $this->makeStudent();
        $this->shareContact('201001234567');
        $already = count($this->sentTexts());

        $qr = $this->getJson(self::BASE."/students/{$student['id']}")->json('data.student.qrCode');
        $body = ['groupId' => (int) $student['groupId'], 'date' => now()->toDateString(), 'qrCode' => $qr];

        $this->postJson(self::BASE.'/attendance/scan', $body)->assertJsonPath('data.parentNotified', true);
        $this->postJson(self::BASE.'/attendance/scan', $body)->assertJsonPath('data.parentNotified', false);
        $this->assertCount(1, array_slice($this->sentTexts(), $already));
    }

    public function test_admin_sees_the_shared_bot_as_active_and_cannot_change_it(): void
    {
        $teacher = Teacher::create(['name' => 'T', 'username' => 'tt', 'password' => 'Str0ng!Pass1']);
        $admin = Admin::create(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => Hash::make('Str0ng!Pass1')]);
        foreach (['view-teachers', 'edit-teacher'] as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']));
        }
        Sanctum::actingAs($admin, ['*'], 'sanctum-admin');

        $this->getJson("/api/v1/admin/teachers/{$teacher->id}/bot")->assertOk()
            ->assertJsonPath('data.bot.username', 'followup_bot')
            ->assertJsonPath('data.bot.status', 'active')
            ->assertJsonPath('data.managerBot', null);
        $this->putJson("/api/v1/admin/teachers/{$teacher->id}/bot", ['name' => 'X', 'username' => 'x_bot'])
            ->assertStatus(409)->assertJsonPath('code', 'bot_shared');
    }

    public function test_a_parent_without_a_linked_account_is_reported_as_not_linked(): void
    {
        $this->actAs();
        $student = $this->makeStudent();

        $this->postJson(self::BASE.'/attendance/close', ['groupId' => (int) $student['groupId'], 'date' => now()->toDateString()])
            ->assertJsonPath('data.parentsNotified', 0)->assertJsonPath('data.parentsNotLinked', 1);
        $this->assertSame([], $this->sentTexts());
    }
}
