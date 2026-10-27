<?php

namespace Modules\Packages\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Packages\app\Console\ExpireSubscriptionsCommand;
use Modules\Packages\app\Models\TeacherSubscription;
use Modules\Packages\app\Services\SubscriptionService;
use Modules\Teachers\app\Contracts\TeacherAccessGate;
use Modules\Teachers\app\Models\Teacher;

class PackagesServiceProvider extends ServiceProvider
{
    protected string $name = 'Packages';

    public function register(): void
    {
        $this->commands([ExpireSubscriptionsCommand::class]);

        // The Teachers module asks this gate on every request; it is what locks out an expired package.
        $this->app->singleton(SubscriptionService::class);
        $this->app->bind(TeacherAccessGate::class, SubscriptionService::class);

        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        Teacher::resolveRelationUsing('subscriptions', fn (Teacher $teacher) => $teacher->hasMany(TeacherSubscription::class));

        // A safety net on top of the per-request check: it also flips the stored status and signs the teacher out.
        $this->app->booted(fn () => $this->app->make(Schedule::class)->command('subscriptions:expire')->hourly()->withoutOverlapping());
    }
}
