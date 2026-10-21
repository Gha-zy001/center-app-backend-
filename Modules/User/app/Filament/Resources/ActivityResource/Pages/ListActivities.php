<?php

namespace Modules\User\app\Filament\Resources\ActivityResource\Pages;

use Modules\User\app\Filament\Resources\ActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            
        ];
    }
}
