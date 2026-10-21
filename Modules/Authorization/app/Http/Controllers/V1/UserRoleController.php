<?php

namespace Modules\Authorization\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Authorization\app\Http\Requests\UserPermissionRequest;
use Modules\Authorization\app\Http\Requests\UserRoleRequest;
use Modules\Authorization\app\Http\Resources\UserRoleResource;
use Modules\Authorization\app\Services\UserRoleService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Admin management of roles and permissions for regular users.
 */
class UserRoleController extends Controller
{
    public function __construct(private UserRoleService $service) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(
            __('messages.roles_fetched_successfully'),
            UserRoleResource::collection($this->service->listRoles())
        );
    }

    public function store(UserRoleRequest $request): JsonResponse
    {
        $role = $this->service->createRole($request->validated('name'), $request->validated('permissions', []));

        return $this->successResponse(__('messages.role_created_successfully'), new UserRoleResource($role), 201);
    }

    public function update(UserRoleRequest $request, Role $role): JsonResponse
    {
        $this->service->ensureUserRole($role);

        $role = $this->service->updateRole(
            $role,
            $request->validated('name'),
            $request->has('permissions') ? $request->validated('permissions', []) : null,
        );

        return $this->successResponse(__('messages.role_updated_successfully'), new UserRoleResource($role));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->service->deleteRole($role);

        return $this->successResponse(__('messages.role_deleted_successfully'));
    }

    public function permissions(): JsonResponse
    {
        return $this->successResponse(
            __('messages.permissions_fetched_successfully'),
            $this->service->listPermissions()->pluck('name')->values()
        );
    }

    public function storePermission(UserPermissionRequest $request): JsonResponse
    {
        $permission = $this->service->createPermission($request->validated('name'));

        return $this->successResponse(__('messages.permission_created_successfully'), ['name' => $permission->name], 201);
    }

    public function destroyPermission(Permission $permission): JsonResponse
    {
        $this->service->deletePermission($permission);

        return $this->successResponse(__('messages.permission_deleted_successfully'));
    }
}
