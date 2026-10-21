<?php

namespace Modules\Blog\app\Filament\Resources\BlogResource\Pages;

use Filament\Actions\CreateAction;
use Modules\Blog\app\Filament\Resources\BlogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlogs extends ListRecords
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
