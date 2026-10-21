<?php

namespace App\Policies\Concerns;

use Modules\Authorization\app\Models\Admin;

/**
 * Maps every Filament policy ability onto four permissions per resource, using the same
 * names as the API routes and the seeder (e.g. "view-users", "edit-user").
 *
 * Classes using this trait define:
 *   protected const PERMISSIONS = ['view' => ..., 'create' => ..., 'edit' => ..., 'delete' => ...];
 *
 * Super admins never reach these checks: Gate::before lets them through.
 */
trait ChecksResourcePermissions
{
    private function allows(Admin $admin, string $action): bool
    {
        $permission = static::PERMISSIONS[$action] ?? null;

        return $permission !== null && $admin->can($permission);
    }

    public function viewAny(Admin $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(Admin $user, mixed $model = null): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(Admin $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function replicate(Admin $user, mixed $model = null): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(Admin $user, mixed $model = null): bool
    {
        return $this->allows($user, 'edit');
    }

    public function reorder(Admin $user): bool
    {
        return $this->allows($user, 'edit');
    }

    public function restore(Admin $user, mixed $model = null): bool
    {
        return $this->allows($user, 'edit');
    }

    public function restoreAny(Admin $user): bool
    {
        return $this->allows($user, 'edit');
    }

    public function delete(Admin $user, mixed $model = null): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(Admin $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function forceDelete(Admin $user, mixed $model = null): bool
    {
        return $this->allows($user, 'delete');
    }

    public function forceDeleteAny(Admin $user): bool
    {
        return $this->allows($user, 'delete');
    }
}
