<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Blog\app\Models\Blog;
use Modules\Blog\app\Models\BlogCategory;
use Modules\Chat\app\Jobs\NotifyChatRecipients;
use Modules\Chat\app\Models\Conversation;
use Modules\Chat\app\Models\Message;
use Modules\Chat\app\Models\Participant;
use Modules\User\app\Models\User;
use Modules\UserNotifications\app\Models\UserNotification;
use Tests\TestCase;

class ModulesPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email): User
    {
        return User::create(['name' => $email, 'email' => $email, 'password' => 'secret', 'status' => 'active', 'email_verified_at' => now()]);
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();

        return count(DB::getQueryLog());
    }

    // ---------------------------------------------------------------- Blog

    public function test_blog_list_query_count_is_constant_and_page_size_is_capped(): void
    {
        $category = BlogCategory::create(['name' => 'News', 'slug' => 'news']);
        foreach (range(1, 5) as $i) {
            Blog::create(['category_id' => $category->id, 'title' => "Post {$i}", 'content' => 'x']);
        }
        $small = $this->countQueries(fn () => $this->getJson('/api/v1/blogs?per_page=100')->assertOk());

        foreach (range(6, 40) as $i) {
            Blog::create(['category_id' => $category->id, 'title' => "Post {$i}", 'content' => 'x']);
        }
        $large = $this->countQueries(fn () => $this->getJson('/api/v1/blogs?per_page=100')->assertOk());

        $this->assertSame($small, $large, "blog list queries grew {$small} -> {$large}: N+1");
        $this->getJson('/api/v1/blogs?per_page=100000')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    // ------------------------------------------------------- Notifications

    public function test_notifications_are_scoped_to_their_owner(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $other = $this->makeUser('other@example.com');
        $note = UserNotification::create(['user_id' => $owner->id, 'type' => 'custom', 'title' => 't', 'body' => 'b']);

        Sanctum::actingAs($other);
        $this->putJson("/api/v1/notifications/{$note->id}/read")->assertNotFound();
        $this->deleteJson("/api/v1/notifications/{$note->id}");
        $this->assertNotNull(UserNotification::find($note->id), 'another user must not delete it');
        $this->assertNull($note->fresh()->read_at);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/notifications/{$note->id}/read")->assertOk();
        $this->assertNotNull($note->fresh()->read_at);
        $this->deleteJson("/api/v1/notifications/{$note->id}")->assertOk();
    }

    public function test_notifications_list_is_capped_and_flat(): void
    {
        $user = $this->makeUser('n@example.com');
        foreach (range(1, 3) as $i) {
            UserNotification::create(['user_id' => $user->id, 'type' => 'custom', 'title' => "t{$i}", 'body' => 'b']);
        }
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications?per_page=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonCount(3, 'data');
    }

    // ---------------------------------------------------------------- Chat

    private function conversationWith(User ...$users): Conversation
    {
        $conversation = Conversation::create(['type' => 'group', 'name' => 'g']);
        foreach ($users as $u) {
            Participant::create(['conversation_id' => $conversation->id, 'user_id' => $u->id]);
        }

        return $conversation;
    }

    public function test_sending_a_message_queues_notifications_instead_of_sending_inline(): void
    {
        Queue::fake();
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');
        $conversation = $this->conversationWith($a, $b);
        Sanctum::actingAs($a);

        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", ['body' => 'hello'])->assertOk();

        Queue::assertPushed(NotifyChatRecipients::class, fn ($job) => $job->conversationId === $conversation->id && $job->senderId === $a->id);
        $this->assertSame(0, UserNotification::count(), 'no notification rows may be created inside the request');
    }

    public function test_queued_job_notifies_everyone_except_the_sender(): void
    {
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');
        $c = $this->makeUser('c@example.com');
        $conversation = $this->conversationWith($a, $b, $c);

        $this->mock(\Modules\UserNotifications\app\Interfaces\NotificationServiceInterface::class, function ($mock) use ($b, $c) {
            $mock->shouldReceive('sendNotification')->once()->with($b->id, 'T', 'B', 'chat_message')->andReturnTrue();
            $mock->shouldReceive('sendNotification')->once()->with($c->id, 'T', 'B', 'chat_message')->andReturnTrue();
        });

        (new NotifyChatRecipients($conversation->id, $a->id, 'T', 'B'))
            ->handle(app(\Modules\UserNotifications\app\Interfaces\NotificationServiceInterface::class));
    }

    public function test_message_list_query_count_is_constant_and_limit_is_capped(): void
    {
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');
        $conversation = $this->conversationWith($a, $b);
        Sanctum::actingAs($a);

        foreach (range(1, 5) as $i) {
            Message::create(['conversation_id' => $conversation->id, 'sender_id' => $b->id, 'body' => "m{$i}", 'type' => 'text']);
        }
        $small = $this->countQueries(fn () => $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages?limit=100")->assertOk());

        foreach (range(6, 60) as $i) {
            Message::create(['conversation_id' => $conversation->id, 'sender_id' => $b->id, 'body' => "m{$i}", 'type' => 'text']);
        }
        $large = $this->countQueries(fn () => $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages?limit=100")->assertOk());

        $this->assertSame($small, $large, "message list queries grew {$small} -> {$large}: N+1");
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages?limit=100000")->assertJsonPath('meta.per_page', 100);
    }

    public function test_conversation_list_returns_a_last_message_for_every_conversation(): void
    {
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');
        Sanctum::actingAs($a);

        foreach (range(1, 3) as $i) {
            $conversation = $this->conversationWith($a, $b);
            Message::create(['conversation_id' => $conversation->id, 'sender_id' => $b->id, 'body' => "hi {$i}", 'type' => 'text']);
        }

        $data = $this->getJson('/api/v1/chat/conversations')->assertOk()->json('data');

        $this->assertCount(3, $data);
        foreach ($data as $row) {
            $this->assertNotNull($row['lastMessage'], 'every conversation must include its last message');
        }
    }

    public function test_chat_requires_authentication_and_never_falls_back_to_user_one(): void
    {
        $this->getJson('/api/v1/chat/conversations')->assertUnauthorized();
    }
}
