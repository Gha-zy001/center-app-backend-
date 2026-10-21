<?php

namespace Modules\Blog\app\Filament\Resources\BlogCategoryResource\Pages;

use Filament\Actions\DeleteAction;
use Modules\Blog\app\Filament\Resources\BlogCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlogCategory extends EditRecord
{
    protected static string $resource = BlogCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
