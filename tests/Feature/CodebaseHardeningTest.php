<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Modules\Authorization\app\Models\Admin;
use Modules\Chat\app\Models\Conversation;
use Modules\Chat\app\Models\Participant;
use Modules\Settings\app\Models\Setting;
use Modules\User\app\Models\RefreshToken;
use Modules\User\app\Models\User;
use Modules\UserNotifications\app\Jobs\SendBulkNotification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CodebaseHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Pass1';

    private function makeUser(string $email = 'member@example.com', array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Member',
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function actingAdmin(array $permissions = []): Admin
    {
        $admin = Admin::create(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'x']);
        foreach ($permissions as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']));
        }
        Sanctum::actingAs($admin, ['*'], 'sanctum-admin');

        return $admin;
    }

    // ------------------------------------------------------------- Media

    public function test_media_endpoints_are_not_public(): void
    {
        $this->postJson('/api/v1/media', [])->assertUnauthorized();
        $this->getJson('/api/v1/media/1')->assertUnauthorized();
        $this->deleteJson('/api/v1/media/1')->assertUnauthorized();
        $this->getJson('/api/v1/media/1/download')->assertUnauthorized();
    }

    public function test_regular_users_cannot_use_media_endpoints(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/v1/media/1')->assertUnauthorized();
    }

    public function test_media_upload_rejects_arbitrary_model_classes_and_script_files(): void
    {
        Storage::fake('public');
        $this->actingAdmin();
        $user = $this->makeUser();

        // A raw class name is no longer accepted: only the allowlisted aliases.
        $this->postJson('/api/v1/media', [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            'model_type' => Admin::class,
            'model_id' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('model_type');

        $this->postJson('/api/v1/media', [
            'file' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
            'model_type' => 'user',
            'model_id' => $user->id,
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    // ------------------------------------------------------ Test-send route

    public function test_push_test_route_is_admin_only(): void
    {
        $user = $this->makeUser();

        $this->getJson("/api/v1/test-send/{$user->id}")->assertUnauthorized();

        Sanctum::actingAs($user);
        $this->getJson("/api/v1/test-send/{$user->id}")->assertUnauthorized();
    }

    // ------------------------------------------------------------ Settings

    public function test_settings_can_be_read_publicly_but_not_written(): void
    {
        Setting::create(['key' => 'site_name', 'value' => 'Old', 'type' => 'string', 'is_public' => true]);

        $this->getJson('/api/v1/settings')->assertOk();

        $this->postJson('/api/v1/settings/site_name', ['value' => 'Hacked'])->assertUnauthorized();
        $this->putJson('/api/v1/settings', ['settings' => [['key' => 'site_name', 'value' => 'Hacked']]])->assertUnauthorized();

        Sanctum::actingAs($this->makeUser());
        $this->postJson('/api/v1/settings/site_name', ['value' => 'Hacked'])->assertUnauthorized();

        $this->assertSame('Old', Setting::where('key', 'site_name')->value('value'));
    }

    public function test_admin_needs_manage_settings_permission(): void
    {
        $this->actingAdmin();
        $this->postJson('/api/v1/settings/site_name', ['value' => 'X'])->assertForbidden();
    }

    public function test_admin_with_permission_can_update_settings(): void
    {
        $this->actingAdmin(['manage-settings']);

        $this->postJson('/api/v1/settings/site_name', ['value' => 'New'])->assertOk();
        $this->assertSame('New', Setting::where('key', 'site_name')->value('value'));
    }

    // -------------------------------------------------------- Social login

    private function fakeSocialUser(string $email, string $id = 'g-1'): void
    {
        $social = Mockery::mock(SocialiteUser::class);
        $social->shouldReceive('getEmail')->andReturn($email);
        $social->shouldReceive('getId')->andReturn($id);
        $social->shouldReceive('getName')->andReturn('Social Person');
        $social->shouldReceive('getNickname')->andReturn(null);
        $social->shouldReceive('getAvatar')->andReturn(null);

        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($social);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_social_sign_up_creates_an_active_plain_user(): void
    {
        $this->fakeSocialUser('new@example.com');

        $this->getJson('/api/login/google/callback')->assertOk()->assertJsonStructure(['data' => ['accessToken']]);

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertSame(['user'], $user->getRoleNames()->all());
    }

    public function test_banned_user_cannot_log_in_with_social_login(): void
    {
        $this->makeUser('banned@example.com', ['status' => 'banned']);
        $this->fakeSocialUser('banned@example.com');

        $this->getJson('/api/login/google/callback')->assertStatus(422);
        $this->assertSame(0, User::where('email', 'banned@example.com')->firstOrFail()->tokens()->count());
    }

    public function test_unknown_social_provider_is_not_routed(): void
    {
        $this->getJson('/api/login/github/callback')->assertNotFound();
    }

    // ------------------------------------------------------ Account delete

    public function test_account_deletion_requires_a_password_instead_of_crashing(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->deleteJson('/api/v1/auth/profile')->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_account_deletion_revokes_every_session(): void
    {
        $user = $this->makeUser();
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])->json('data');

        $this->withToken($login['accessToken'])
            ->deleteJson('/api/v1/auth/profile', ['password' => self::PASSWORD])
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSame(0, RefreshToken::where('user_id', $user->id)->where('is_revoked', false)->count());
        $this->postJson('/api/v1/auth/refresh-token', ['refresh_token' => $login['refreshToken']])->assertUnauthorized();
    }

    // -------------------------------------------------------- Broadcasting

    public function test_only_participants_are_authorized_on_a_conversation_channel(): void
    {
        $member = $this->makeUser('in@example.com');
        $stranger = $this->makeUser('out@example.com');
        $conversation = Conversation::create(['type' => 'direct']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $member->id]);

        $channels = Broadcast::getChannels();
        $this->assertTrue($channels->has('conversation.{conversationId}'), 'the conversation channel must be registered');
        $callback = $channels->get('conversation.{conversationId}');

        $this->assertSame(['id' => $member->id, 'name' => $member->name], $callback($member, $conversation->id));
        $this->assertFalse($callback($stranger, $conversation->id));
    }

    // ------------------------------------------------------- Bulk notices

    public function test_bulk_notification_job_notifies_each_user(): void
    {
        $a = $this->makeUser('a@example.com');
        $b = $this->makeUser('b@example.com');

        (new SendBulkNotification([$a->id, $b->id], 'Hello', 'World'))
            ->handle(app(\Modules\UserNotifications\app\Interfaces\NotificationServiceInterface::class));

        $this->assertDatabaseCount('user_notifications', 2);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $b->id, 'type' => 'admin_broadcast', 'title' => 'Hello']);
    }

    // ------------------------------------------------------ Config caching

    public function test_payment_and_sms_settings_are_read_through_config(): void
    {
        config(['services.payment.provider' => 'unknown-provider', 'services.sms.provider' => 'unknown-sms']);

        $this->expectExceptionMessage('unknown-provider');
        \App\Services\Payment\PaymentFactory::make();
    }
}
