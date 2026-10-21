<?php

namespace Modules\Blog\app\Filament\Resources\BlogCategoryResource\Pages;

use Modules\Blog\app\Filament\Resources\BlogCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogCategory extends CreateRecord
{
    protected static string $resource = BlogCategoryResource::class;
}
