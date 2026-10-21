<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authorization\app\Models\Admin;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Opens every admin panel page as a super admin, so runtime errors in resources,
 * pages and policies (e.g. wrong type hints) fail the build.
 */
class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_panel_page_renders_for_a_super_admin(): void
    {
        $admin = Admin::create(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'x']);
        $admin->assignRole(Role::create(['name' => Admin::SUPER_ADMIN_ROLE, 'guard_name' => 'admin']));
        $this->actingAs($admin, 'admin');

        $paths = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods())
                && str_starts_with($r->uri(), 'admin')
                && ! str_contains($r->uri(), '{')
                && ! str_contains($r->uri(), 'logout')
                && ! str_contains($r->uri(), 'login'))
            ->map(fn ($r) => '/' . $r->uri())
            ->unique()
            ->values();

        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $status = $this->get($path)->status();
            $this->assertLessThan(400, $status, "{$path} returned {$status}");
        }
    }

    private function actingSuperAdmin(): void
    {
        $admin = Admin::create(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'x']);
        $admin->assignRole(Role::create(['name' => Admin::SUPER_ADMIN_ROLE, 'guard_name' => 'admin']));
        $this->actingAs($admin, 'admin');
    }

    public function test_saving_settings_in_the_panel_is_visible_in_the_api(): void
    {
        $this->actingSuperAdmin();
        \Modules\Settings\app\Models\Setting::create(['key' => 'app_name', 'value' => 'Old', 'type' => 'string', 'is_public' => true]);

        // Warm the API cache with the old value.
        $this->getJson('/api/v1/settings')->assertJsonPath('data.0.value', 'Old');

        \Livewire\Livewire::test(\Modules\Settings\app\Filament\Pages\ManageSettings::class)
            ->set('data.app_name', 'New')
            ->set('data.contact_email', 'hello@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New', \Modules\Settings\app\Models\Setting::where('key', 'app_name')->value('value'));
        // A key that had no row yet is created instead of silently dropped.
        $this->assertSame('hello@example.com', \Modules\Settings\app\Models\Setting::where('key', 'contact_email')->value('value'));

        $values = collect($this->getJson('/api/v1/settings')->json('data'))->pluck('value', 'key');
        $this->assertSame('New', $values['app_name'], 'the API must not serve the stale cached value');
    }
    public function test_send_notification_page_queues_chunked_jobs(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->actingSuperAdmin();
        foreach (range(1, 3) as $i) {
            \Modules\User\app\Models\User::create(['name' => "U{$i}", 'email' => "u{$i}@x.com", 'password' => 'x', 'status' => 'active']);
        }

        \Livewire\Livewire::test(\Modules\UserNotifications\app\Filament\Pages\SendNotification::class)
            ->set('data.send_to', 'all')
            ->set('data.title', 'Hi')
            ->set('data.message', 'There')
            ->callAction('send')
            ->assertHasNoErrors();

        \Illuminate\Support\Facades\Queue::assertPushed(
            \Modules\UserNotifications\app\Jobs\SendBulkNotification::class,
            fn ($job) => count($job->userIds) === 3 && $job->title === 'Hi'
        );
        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_panel_rejects_guests(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
