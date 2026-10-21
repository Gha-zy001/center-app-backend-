<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Activity log (read and prune only; entries are never created or edited by hand).
 */
class ActivityPolicy
{
    use ChecksResourcePermissions, HandlesAuthorization;

    protected const PERMISSIONS = [
        'view' => 'view-activity',
        'delete' => 'delete-activity',
    ];
}
