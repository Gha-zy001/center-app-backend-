<?php

namespace Modules\Authorization\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Authorization\app\Models\Admin;
use Spatie\Permission\Models\Role;

/**
 * The first super admin: the center's management, who creates the teachers.
 *
 * Credentials come from the environment so none live in the repository:
 *   SEED_ADMIN_EMAIL     (default admin@example.com)
 *   SEED_ADMIN_NAME      (default Super Admin)
 *   SEED_ADMIN_PASSWORD  (when empty, a random one is generated and printed once)
 *
 * An existing admin is never touched, so running this again cannot reset a password.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) env('SEED_ADMIN_EMAIL', 'admin@example.com')));

        if (Admin::where('email', $email)->exists()) {
            $this->command?->info("Super admin {$email} already exists: left unchanged.");

            return;
        }

        $password = env('SEED_ADMIN_PASSWORD') ?: Str::password(16, symbols: false);

        $admin = Admin::create([
            'name' => env('SEED_ADMIN_NAME', 'Super Admin'),
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $role = Role::where('name', 'super-admin')->where('guard_name', 'admin')->first();

        if ($role) {
            $admin->assignRole($role);
        }

        $this->command?->info("Super admin created: {$email}");

        if (! env('SEED_ADMIN_PASSWORD')) {
            $this->command?->warn("Generated password (shown once): {$password}");
        }
    }
}
