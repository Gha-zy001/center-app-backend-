<?php

namespace Modules\Authorization\Database\Seeders;

use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Admin roles and permissions use the "admin" guard; regular users only get the
     * permission-less "user" role under the "web" guard.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // One naming scheme for the API routes and the admin panel policies.
        $permissions = [
            'view-users',
            'create-user',
            'edit-user',
            'delete-user',

            'view-roles',
            'assign-role',
            'create-role',
            'edit-role',
            'delete-role',

            // Roles and permissions of regular users.
            'manage-user-roles',

            'view-blogs',
            'create-blog',
            'edit-blog',
            'delete-blog',

            'view-blog-categories',
            'create-blog-category',
            'edit-blog-category',
            'delete-blog-category',

            'view-activity',
            'delete-activity',

            'manage-settings',
            'send-notifications',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);

        $superAdmin->givePermissionTo(Permission::where('guard_name', 'admin')->get());
        $admin->givePermissionTo($permissions);

        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    }

}
