<?php

namespace Modules\Blog\app\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Blog categories.
 */
class BlogCategoryPolicy
{
    use ChecksResourcePermissions, HandlesAuthorization;

    protected const PERMISSIONS = [
        'view' => 'view-blog-categories',
        'create' => 'create-blog-category',
        'edit' => 'edit-blog-category',
        'delete' => 'delete-blog-category',
    ];
}
