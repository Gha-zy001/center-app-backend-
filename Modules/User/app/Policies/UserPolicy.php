<?php

namespace Modules\User\app\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Users.
 */
class UserPolicy
{
    use ChecksResourcePermissions, HandlesAuthorization;

    protected const PERMISSIONS = [
        'view' => 'view-users',
        'create' => 'create-user',
        'edit' => 'edit-user',
        'delete' => 'delete-user',
    ];
}
