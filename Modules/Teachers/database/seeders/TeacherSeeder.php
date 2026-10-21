<?php

namespace Modules\Teachers\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Teachers\app\Models\Teacher;

/**
 * A first teacher account (each teacher is a tenant with their own students and groups).
 *
 * Credentials come from the environment so none live in the repository:
 *   SEED_TEACHER_USERNAME  (default teacher)
 *   SEED_TEACHER_NAME      (default Teacher)
 *   SEED_TEACHER_PHONE     (optional)
 *   SEED_TEACHER_PASSWORD  (when empty, a random one is generated and printed once)
 *
 * An existing teacher is never touched, so running this again cannot reset a password.
 */
class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $username = strtolower(trim((string) env('SEED_TEACHER_USERNAME', 'teacher')));

        if (Teacher::withTrashed()->where('username', $username)->exists()) {
            $this->command?->info("Teacher {$username} already exists: left unchanged.");

            return;
        }

        $password = env('SEED_TEACHER_PASSWORD') ?: Str::password(16, symbols: false);

        Teacher::create([
            'name' => env('SEED_TEACHER_NAME', 'Teacher'),
            'username' => $username,
            'password' => $password,
            'phone' => env('SEED_TEACHER_PHONE') ?: null,
        ]);

        $this->command?->info("Teacher created: {$username}");

        if (! env('SEED_TEACHER_PASSWORD')) {
            $this->command?->warn("Generated password (shown once): {$password}");
        }
    }
}
