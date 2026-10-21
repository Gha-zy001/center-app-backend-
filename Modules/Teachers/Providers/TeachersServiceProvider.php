<?php

namespace Modules\Teachers\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Teachers\app\Support\TeacherContext;

class TeachersServiceProvider extends ServiceProvider
{
    protected string $name = 'Teachers';

    public function register(): void
    {
        // One ambient teacher per request, set by ResolveTeacherContext and read by the
        // BelongsToTeacher scope. Scoped so it can never leak between requests or jobs.
        $this->app->scoped(TeacherContext::class);

        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
