<?php

namespace Modules\Blog\app\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Blog posts.
 */
class BlogPolicy
{
    use ChecksResourcePermissions, HandlesAuthorization;

    protected const PERMISSIONS = [
        'view' => 'view-blogs',
        'create' => 'create-blog',
        'edit' => 'edit-blog',
        'delete' => 'delete-blog',
    ];
}
