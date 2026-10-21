<?php

namespace Modules\Authorization\app\Services;

use Spatie\Permission\Models\Role;

class RoleService
{
    public function list()
    {
        return Role::with('permissions')->where('guard_name', 'admin')->get();
    }

    public function create(array $data)
    {
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'admin']);
        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }
        return $role->load('permissions');
    }

    public function update($role, array $data)
    {
        $role->update(['name' => $data['name']]);
        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }
        return $role->load('permissions');
    }

    public function delete($role)
    {
        return $role->delete();
    }
}