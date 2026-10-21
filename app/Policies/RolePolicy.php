<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Roles.
 */
class RolePolicy
{
    use ChecksResourcePermissions, HandlesAuthorization;

    protected const PERMISSIONS = [
        'view' => 'view-roles',
        'create' => 'create-role',
        'edit' => 'edit-role',
        'delete' => 'delete-role',
    ];
}
