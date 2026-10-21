<?php

namespace Modules\User\app\Filament\Resources\UserPermissionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Authorization\app\Services\UserRoleService;
use Modules\User\app\Filament\Resources\UserPermissionResource;
use Modules\User\app\Filament\Resources\UserRoleResource;

class ManageUserPermissions extends ManageRecords
{
    protected static string $resource = UserPermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data) => [...$data, 'guard_name' => UserRoleService::GUARD])
                ->after(fn () => UserRoleResource::forgetPermissionCache()),
        ];
    }
}
