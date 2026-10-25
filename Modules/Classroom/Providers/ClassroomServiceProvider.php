<?php

namespace Modules\Classroom\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Classroom\app\Console\PollTelegramCommand;
use Modules\Classroom\app\Console\SetManagerWebhookCommand;
use Modules\Classroom\app\Console\SetTelegramWebhookCommand;
use Modules\Classroom\app\Models\Group;
use Modules\Classroom\app\Models\Student;
use Modules\Teachers\app\Models\Teacher;

class ClassroomServiceProvider extends ServiceProvider
{
    protected string $name = 'Classroom';

    public function register(): void
    {
        $this->commands([PollTelegramCommand::class, SetTelegramWebhookCommand::class, SetManagerWebhookCommand::class]);

        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        // The admin sees how much each teacher uses the system. The relations skip the teacher
        // scope on purpose: an admin request has no teacher context, so the scope would match nothing.
        Teacher::resolveRelationUsing('students', fn (Teacher $teacher) => $teacher->hasMany(Student::class)->withoutGlobalScopes());
        Teacher::resolveRelationUsing('groups', fn (Teacher $teacher) => $teacher->hasMany(Group::class)->withoutGlobalScopes());

        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
