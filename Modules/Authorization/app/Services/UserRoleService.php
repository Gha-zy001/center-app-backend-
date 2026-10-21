<?php

namespace Modules\Authorization\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles and permissions for regular users ("web" guard), managed by admins.
 * Admin roles live under the "admin" guard and are handled by RoleService.
 */
class UserRoleService
{
    public const GUARD = 'web';

    /**
     * Every self-registered and social user gets this role, so it cannot be renamed or deleted.
     */
    public const DEFAULT_ROLE = 'user';

    public function listRoles(): Collection
    {
        return Role::where('guard_name', self::GUARD)
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    public function createRole(string $name, array $permissions = []): Role
    {
        return DB::transaction(function () use ($name, $permissions) {
            $role = Role::create(['name' => $name, 'guard_name' => self::GUARD]);
            $role->syncPermissions($this->permissionModels($permissions));

            return $role->load('permissions');
        });
    }

    public function updateRole(Role $role, ?string $name, ?array $permissions): Role
    {
        $this->ensureUserRole($role);

        if ($name !== null && $name !== $role->name) {
            $this->ensureNotDefault($role);
        }

        return DB::transaction(function () use ($role, $name, $permissions) {
            if ($name !== null) {
                $role->update(['name' => $name]);
            }

            if ($permissions !== null) {
                $role->syncPermissions($this->permissionModels($permissions));
            }

            return $role->load('permissions');
        });
    }

    public function deleteRole(Role $role): void
    {
        $this->ensureUserRole($role);
        $this->ensureNotDefault($role);

        $role->delete();
    }

    public function listPermissions(): Collection
    {
        return Permission::where('guard_name', self::GUARD)->orderBy('name')->get();
    }

    public function createPermission(string $name): Permission
    {
        return Permission::create(['name' => $name, 'guard_name' => self::GUARD]);
    }

    public function deletePermission(Permission $permission): void
    {
        if ($permission->guard_name !== self::GUARD) {
            abort(404);
        }

        $permission->delete();
    }

    public function ensureUserRole(Role $role): void
    {
        // Route model binding resolves any role; admin roles must never be touched here.
        if ($role->guard_name !== self::GUARD) {
            abort(404);
        }
    }

    private function ensureNotDefault(Role $role): void
    {
        if ($role->name === self::DEFAULT_ROLE) {
            throw ValidationException::withMessages([
                'name' => __('messages.default_user_role_locked'),
            ]);
        }
    }

    /**
     * Resolve names to web-guard permission models, so an admin permission can never be attached.
     */
    private function permissionModels(array $names): Collection
    {
        return Permission::where('guard_name', self::GUARD)->whereIn('name', $names)->get();
    }
}
