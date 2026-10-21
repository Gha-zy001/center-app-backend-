<?php

namespace Modules\Blog\app\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Modules\Blog\app\Filament\Resources\BlogCategoryResource\Pages\ListBlogCategories;
use Modules\Blog\app\Filament\Resources\BlogCategoryResource\Pages\CreateBlogCategory;
use Modules\Blog\app\Filament\Resources\BlogCategoryResource\Pages\EditBlogCategory;
use Modules\Blog\app\Filament\Resources\BlogCategoryResource\Pages;
use Modules\Blog\app\Models\BlogCategory;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;

class BlogCategoryResource extends Resource
{
    protected static ?string $model = BlogCategory::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';




    public static function getNavigationLabel(): string
    {
        return __('admin.blog_categories');
    }

    public static function getModelLabel(): string
    {
        return __('admin.blog_category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.blog_categories');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.blog_category'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.category_name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, $record) {
                                $set('slug', BlogCategory::generateUniqueSlug($state, $record?->id ?? 0));
                            }),

                        TextInput::make('slug')
                            ->label(__('admin.category_slug'))
                            ->required()
                            ->maxLength(255)
                            ->afterStateUpdated(function (Set $set, ?string $state, $record) {
                                $set('slug', BlogCategory::generateUniqueSlug($state, $record?->id ?? 0));
                            }),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.category_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label(__('admin.category_slug'))
                    ->copyable(),

                TextColumn::make('created_at')
                    ->label(__('admin.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogCategories::route('/'),
            'create' => CreateBlogCategory::route('/create'),
            'edit' => EditBlogCategory::route('/{record}/edit'),
        ];
    }
}