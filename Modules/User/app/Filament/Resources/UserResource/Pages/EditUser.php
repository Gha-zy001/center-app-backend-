<?php

namespace Modules\User\app\Filament\Resources\UserResource\Pages;

use Filament\Actions\DeleteAction;
use Modules\User\app\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
