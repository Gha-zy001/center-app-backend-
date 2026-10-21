<?php

namespace Modules\Authorization\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Modules\Authorization\app\Http\Requests\CreateRoleRequest;
use Modules\Authorization\app\Http\Requests\UpdateRoleRequest;
use Modules\Authorization\app\Services\RoleService;
use Modules\Authorization\app\Http\Resources\RoleResource;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    protected $service;

    public function __construct(RoleService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $roles = $this->service->list();
        return $this->successResponse(__('messages.roles_fetched_successfully'), RoleResource::collection($roles));
    }

    public function store(CreateRoleRequest $request)
    {
        $role = $this->service->create($request->validated());
        return $this->successResponse(__('messages.role_created_successfully'), new RoleResource($role));
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        abort_unless($role->guard_name === 'admin', 404);

        $role = $this->service->update($role, $request->validated());
        return $this->successResponse(__('messages.role_updated_successfully'), new RoleResource($role));
    }

    public function destroy(Role $role)
    {
        abort_unless($role->guard_name === 'admin', 404);

        $this->service->delete($role);
        return $this->successResponse(__('messages.role_deleted_successfully'));
    }
}