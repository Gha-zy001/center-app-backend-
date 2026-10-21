<?php

namespace Modules\Blog\app\Filament\Resources\BlogResource\Pages;

use Modules\Blog\app\Filament\Resources\BlogResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    protected static string $resource = BlogResource::class;
}
