<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Authorization\app\Models\Admin;
use Modules\User\app\Models\User;

return new class extends Migration
{
    /**
     * Roles that used to grant back-office access to a row in the users table.
     */
    private const ADMIN_ROLE_NAMES = ['super-admin', 'admin'];

    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $this->moveAdminUsersToAdmins();
        $this->removeShieldPanelUserRole();
    }

    /**
     * Filament Shield's HasPanelShield trait attached "panel_user" to every user created over HTTP.
     * It is not an admin role (every user had it), so it is removed rather than migrated.
     */
    private function removeShieldPanelUserRole(): void
    {
        $roleIds = DB::table('roles')->where('name', 'panel_user')->pluck('id');

        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('roles')->whereIn('id', $roleIds)->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }

    /**
     * Copy every user that holds an admin role into admins, mirror the roles and permissions
     * under the "admin" guard, then strip the admin roles from the users table side.
     * Nothing is deleted from users.
     */
    private function moveAdminUsersToAdmins(): void
    {
        $webRoles = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', self::ADMIN_ROLE_NAMES)
            ->get();

        if ($webRoles->isEmpty()) {
            return;
        }

        $now = now();

        foreach (DB::table('permissions')->where('guard_name', 'web')->get() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission->name, 'guard_name' => 'admin'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $adminRoleIds = [];
        foreach ($webRoles as $role) {
            $adminRoleName = $role->name;

            DB::table('roles')->updateOrInsert(
                ['name' => $adminRoleName, 'guard_name' => 'admin'],
                ['created_at' => $now, 'updated_at' => $now],
            );
            $adminRoleId = DB::table('roles')->where(['name' => $adminRoleName, 'guard_name' => 'admin'])->value('id');
            $adminRoleIds[$role->id] = $adminRoleId;

            $permissionNames = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $role->id)
                ->pluck('permissions.name');

            foreach ($permissionNames as $name) {
                $adminPermissionId = DB::table('permissions')->where(['name' => $name, 'guard_name' => 'admin'])->value('id');
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $adminPermissionId,
                    'role_id' => $adminRoleId,
                ]);
            }
        }

        $assignments = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereIn('role_id', $webRoles->pluck('id'))
            ->get()
            ->groupBy('model_id');

        foreach ($assignments as $userId => $rows) {
            $user = DB::table('users')->where('id', $userId)->first();
            if (! $user) {
                continue;
            }

            DB::table('admins')->insertOrIgnore([
                'name' => $user->name,
                'email' => $user->email,
                'password' => $user->password,
                'is_active' => true,
                'created_at' => $user->created_at ?? $now,
                'updated_at' => $now,
            ]);
            $adminId = DB::table('admins')->where('email', $user->email)->value('id');

            foreach ($rows as $row) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $adminRoleIds[$row->role_id],
                    'model_type' => Admin::class,
                    'model_id' => $adminId,
                ]);
            }
        }

        // Strip: users no longer hold admin roles, and the web-guard copies are removed so they
        // can never be assigned to a user again (their permission links cascade).
        DB::table('model_has_roles')->whereIn('role_id', $webRoles->pluck('id'))->delete();
        DB::table('roles')->whereIn('id', $webRoles->pluck('id'))->delete();
    }
};
