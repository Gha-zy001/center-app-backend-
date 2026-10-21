<?php

namespace Modules\Blog\app\Filament\Resources\BlogResource\Pages;

use Filament\Actions\DeleteAction;
use Modules\Blog\app\Filament\Resources\BlogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlog extends EditRecord
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
