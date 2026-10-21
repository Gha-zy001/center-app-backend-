<?php

namespace Modules\Authorization\app\Services;

use Illuminate\Support\Facades\Hash;
use Modules\User\app\Models\User;

class UserService
{
    public function list()
    {
        return User::with('roles')->get();
    }

    public function create(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        if (!empty($data['roles'])) {
            $user->assignRole($data['roles']);
        }

        if (!empty($data['permissions'])) {
            $user->givePermissionTo($data['permissions']);
        }

        return $user->load('roles', 'permissions');
    }

    public function update($user, array $data)
    {
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return $user->load('roles', 'permissions');
    }

    public function delete($user)
    {
        return $user->delete();
    }
}