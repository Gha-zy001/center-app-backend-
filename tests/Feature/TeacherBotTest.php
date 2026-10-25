<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Classroom\app\Models\TeacherBot;
use Modules\Teachers\app\Models\Teacher;
use Tests\TestCase;

class TeacherBotTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/teacher';

    private const MANAGER_SECRET = 'manager-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.token' => 'SHARED:TOKEN',
            'services.telegram.webhook_secret' => 'shared-secret',
            'services.telegram.manager_token' => 'MANAGER:TOKEN',
            'services.telegram.manager_username' => 'CenterManagerBot',
            'services.telegram.manager_webhook_secret' => self::MANAGER_SECRET,
        ]);
        Http::fake([
            'api.telegram.org/botMANAGER:TOKEN/getManagedBotToken' => Http::response(['ok' => true, 'result' => 'NEW:BOTTOKEN']),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);
    }

    private function actAs(string $username = 'ahmed'): Teacher
    {
        $teacher = Teacher::firstOrCreate(['username' => $username], ['name' => ucfirst($username), 'password' => 'Str0ng!Pass1']);
        Sanctum::actingAs($teacher, ['*'], 'sanctum-teacher');

        return $teacher;
    }

    private function manager(array $update)
    {
        return $this->postJson('/api/v1/telegram/manager/webhook', $update, ['X-Telegram-Bot-Api-Secret-Token' => self::MANAGER_SECRET]);
    }

    private function sentTo(string $token): array
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), "bot{$token}/sendMessage"))
            ->map(fn ($pair) => $pair[0]->data())->values()->all();
    }

    // Walks the whole creation flow for the signed-in teacher and returns the stored bot.
    private function createBot(int $telegramUserId = 900): TeacherBot
    {
        $link = $this->postJson(self::BASE.'/bot/connect')->assertOk()->json('data.startLink');
        $this->assertStringStartsWith('https://t.me/CenterManagerBot?start=', $link);
        $code = substr($link, strrpos($link, '=') + 1);

        $this->manager(['message' => ['chat' => ['id' => $telegramUserId, 'type' => 'private'], 'from' => ['id' => $telegramUserId], 'text' => "/start $code"]])->assertOk();
        $this->manager(['managed_bot' => ['user' => ['id' => $telegramUserId], 'bot' => ['id' => 777, 'username' => 'ahmed_parents_bot']]])->assertOk();

        return TeacherBot::firstOrFail();
    }

    public function test_teacher_creates_a_bot_through_the_manager_bot(): void
    {
        $this->actAs();
        config(['services.telegram.bot_username' => 'shared_bot']);
        $this->getJson(self::BASE.'/bot')->assertJsonPath('data.bot', null)->assertJsonPath('data.managerConfigured', true)
            ->assertJsonPath('data.parentsLink', 'https://t.me/shared_bot');

        $bot = $this->createBot();
        $this->getJson(self::BASE.'/bot')->assertJsonPath('data.parentsLink', 'https://t.me/ahmed_parents_bot');

        $this->assertSame('NEW:BOTTOKEN', $bot->token);
        $this->assertDatabaseMissing('teacher_bots', ['token' => 'NEW:BOTTOKEN']); // stored encrypted
        $this->getJson(self::BASE.'/bot')->assertJsonPath('data.bot.username', 'ahmed_parents_bot')
            ->assertJsonPath('data.bot.link', 'https://t.me/ahmed_parents_bot');
        $this->assertStringNotContainsString('NEW:BOTTOKEN', $this->getJson(self::BASE.'/bot')->getContent());
        $this->assertDatabaseCount('teacher_bot_requests', 0);

        // The start link answered with the create-bot link holding the suggested username.
        $this->assertStringContainsString('t.me/newbot/CenterManagerBot/ahmed_parents_bot', json_encode($this->sentTo('MANAGER:TOKEN')[0], JSON_UNESCAPED_SLASHES));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'botNEW:BOTTOKEN/setWebhook')
            && str_contains($request['url'], '/api/v1/telegram/bot/'.$bot->webhook_key)
            && $request['secret_token'] === $bot->webhook_secret);
    }

    public function test_manager_webhook_needs_its_secret_and_ignores_strangers(): void
    {
        $this->postJson('/api/v1/telegram/manager/webhook', [])->assertForbidden();
        $this->postJson('/api/v1/telegram/manager/webhook', [], ['X-Telegram-Bot-Api-Secret-Token' => 'nope'])->assertForbidden();

        // A bot nobody requested through the app, and an expired / unknown start code, create nothing.
        $this->manager(['managed_bot' => ['user' => ['id' => 5], 'bot' => ['id' => 6, 'username' => 'stranger_bot']]])->assertOk();
        $this->manager(['message' => ['chat' => ['id' => 5, 'type' => 'private'], 'from' => ['id' => 5], 'text' => '/start '.str_repeat('a', 32)]])->assertOk();
        $this->assertDatabaseCount('teacher_bots', 0);
    }

    public function test_one_bot_can_be_both_manager_and_shared_parents_bot(): void
    {
        config([
            'services.telegram.manager_token' => 'SHARED:TOKEN',
            'services.telegram.manager_webhook_secret' => 'shared-secret',
        ]);
        $this->actAs();
        $student = $this->makeStudent('Omar', '01001234567');

        $update = ['message' => ['chat' => ['id' => 555, 'type' => 'private'], 'from' => ['id' => 555],
            'contact' => ['phone_number' => '201001234567', 'user_id' => 555]]];
        $this->postJson('/api/v1/telegram/manager/webhook', $update, ['X-Telegram-Bot-Api-Secret-Token' => 'shared-secret'])->assertOk();

        $this->assertDatabaseHas('student_telegram_contacts', ['student_id' => $student['id'], 'chat_id' => '555', 'teacher_bot_id' => null]);
        $this->assertCount(1, $this->sentTo('SHARED:TOKEN'));
    }

    public function test_generated_link_stays_visible_until_the_bot_exists(): void
    {
        $this->actAs();
        $this->getJson(self::BASE.'/bot')->assertJsonPath('data.pending', null);

        $link = $this->postJson(self::BASE.'/bot/connect')->assertOk()->json('data.startLink');
        $this->getJson(self::BASE.'/bot')->assertJsonPath('data.pending.startLink', $link);
        $this->postJson(self::BASE.'/bot/connect')->assertJsonPath('data.startLink', $link); // same link, not a new one

        $this->travel(30)->days();
        $this->getJson(self::BASE.'/bot')->assertJsonPath('data.pending.startLink', $link); // never expires
        $this->travelBack();

        $this->createBot();
        $this->getJson(self::BASE.'/bot')->assertJsonPath('data.pending', null)->assertJsonPath('data.bot.username', 'ahmed_parents_bot');
    }

    public function test_connect_needs_the_manager_bot_configured(): void
    {
        $this->actAs();
        config(['services.telegram.manager_username' => '']);
        $this->postJson(self::BASE.'/bot/connect')->assertStatus(503)->assertJsonPath('code', 'manager_bot_not_configured');
    }

    public function test_parents_link_through_the_teachers_bot_only_to_that_teachers_students(): void
    {
        $this->actAs('ahmed');
        $bot = $this->createBot();
        $mine = $this->makeStudent('Mine', '01001234567');

        $this->actAs('sara');
        $this->makeStudent('Hers', '01001234567');

        $url = '/api/v1/telegram/bot/'.$bot->webhook_key;
        $contact = ['message' => ['chat' => ['id' => 555, 'type' => 'private'], 'from' => ['id' => 555, 'first_name' => 'Ali'],
            'contact' => ['phone_number' => '201001234567', 'user_id' => 555]]];

        $this->postJson($url, $contact)->assertForbidden();
        $this->postJson($url, $contact, ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])->assertForbidden();
        $this->postJson('/api/v1/telegram/bot/'.str_repeat('x', 40), $contact, ['X-Telegram-Bot-Api-Secret-Token' => $bot->webhook_secret])->assertForbidden();

        $this->postJson($url, $contact, ['X-Telegram-Bot-Api-Secret-Token' => $bot->webhook_secret])->assertOk();

        $this->assertDatabaseCount('student_telegram_contacts', 1);
        $this->assertDatabaseHas('student_telegram_contacts', ['student_id' => $mine['id'], 'chat_id' => '555', 'teacher_bot_id' => $bot->id]);
        $this->assertCount(1, $this->sentTo('NEW:BOTTOKEN')); // the reply came from the teacher's own bot
        $this->assertCount(0, $this->sentTo('SHARED:TOKEN'));
    }

    public function test_grades_reach_the_parent_through_the_teachers_bot(): void
    {
        $this->actAs('ahmed');
        $bot = $this->createBot();
        $student = $this->makeStudent('Omar', '01001234567');
        $this->linkParent($bot);
        $before = count($this->sentTo('NEW:BOTTOKEN'));

        $exam = $this->postJson(self::BASE.'/exams', ['groupId' => (int) $student['groupId'], 'name' => 'Quiz', 'maxScore' => 20, 'date' => now()->toDateString()])
            ->assertCreated()->json('data.exam');
        $this->putJson(self::BASE."/exams/{$exam['id']}/grades/{$student['id']}", ['score' => 15])->assertOk();

        $this->assertCount($before + 1, $this->sentTo('NEW:BOTTOKEN'));
        $this->assertCount(0, $this->sentTo('SHARED:TOKEN'));
    }

    public function test_removing_the_bot_drops_it_and_its_parents(): void
    {
        $this->actAs();
        $bot = $this->createBot();
        $this->makeStudent('Omar', '01001234567');
        $this->linkParent($bot);

        $this->deleteJson(self::BASE.'/bot')->assertOk();

        $this->assertDatabaseCount('teacher_bots', 0);
        $this->assertDatabaseCount('student_telegram_contacts', 0);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'botNEW:BOTTOKEN/deleteWebhook'));
    }

    public function test_bot_endpoints_require_a_teacher_token(): void
    {
        $this->getJson(self::BASE.'/bot')->assertUnauthorized();
        $this->postJson(self::BASE.'/bot/connect')->assertUnauthorized();
    }

    private function linkParent(TeacherBot $bot): void
    {
        $this->postJson('/api/v1/telegram/bot/'.$bot->webhook_key, ['message' => [
            'chat' => ['id' => 555, 'type' => 'private'], 'from' => ['id' => 555],
            'contact' => ['phone_number' => '201001234567', 'user_id' => 555],
        ]], ['X-Telegram-Bot-Api-Secret-Token' => $bot->webhook_secret])->assertOk();
    }

    private function makeStudent(string $name, string $parentPhone): array
    {
        $group = $this->postJson(self::BASE.'/groups', ['name' => 'Math '.$name, 'days' => ['sat'], 'time' => '17:30'])->json('data.group');

        return $this->postJson(self::BASE.'/students', ['name' => $name, 'phone' => '', 'parentPhone' => $parentPhone, 'groupId' => (int) $group['id']])
            ->assertCreated()->json('data.student');
    }
}
