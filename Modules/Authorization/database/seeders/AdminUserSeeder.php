<?php

namespace Modules\Authorization\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Authorization\app\Models\Admin;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = 'admin@example.com';

        $admin = Admin::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );

        $role = Role::where('name', 'super-admin')->where('guard_name', 'admin')->first();

        if ($role && !$admin->hasRole($role)) {
            $admin->assignRole($role);
        }
    }
}
