<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Authorization\Database\Seeders\AdminUserSeeder;
use Modules\Authorization\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Teachers\Database\Seeders\TeacherSeeder;

/**
 * The first accounts of a fresh install: roles and permissions, the super admin, and one teacher.
 * Safe to run again: existing accounts are left unchanged.
 *
 *   php artisan db:seed --class=ProductionSeeder --force
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            TeacherSeeder::class,
        ]);
    }
}
