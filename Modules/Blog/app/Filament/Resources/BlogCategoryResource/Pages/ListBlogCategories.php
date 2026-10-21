<?php

namespace Modules\Blog\app\Filament\Resources\BlogCategoryResource\Pages;

use Filament\Actions\CreateAction;
use Modules\Blog\app\Filament\Resources\BlogCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlogCategories extends ListRecords
{
    protected static string $resource = BlogCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
