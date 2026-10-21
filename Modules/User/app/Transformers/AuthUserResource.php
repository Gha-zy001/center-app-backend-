<?php

namespace Modules\User\app\Transformers;

use Illuminate\Http\Request;

/**
 * The signed-in user's own view: the base user fields plus everything they are allowed to do.
 * Used for register, login, refresh and profile responses, not for lists.
 */
class AuthUserResource extends UserResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
