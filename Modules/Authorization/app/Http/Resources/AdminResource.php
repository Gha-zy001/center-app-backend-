<?php

namespace Modules\Authorization\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

class AdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSuperAdmin = $this->isSuperAdmin();

        // A super admin can do everything, so the client receives every admin permission,
        // not only the ones assigned to the role.
        $permissions = $isSuperAdmin
            ? Permission::where('guard_name', 'admin')->pluck('name')
            : $this->getAllPermissions()->pluck('name');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'isActive' => $this->is_active,
            'isSuperAdmin' => $isSuperAdmin,
            'lastLoginAt' => $this->last_login_at?->toDateTimeString(),
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $permissions->values(),
            'createdAt' => $this->created_at?->toDateTimeString(),
        ];
    }
}
