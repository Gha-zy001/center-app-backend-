<?php

namespace Modules\Blog\app\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Modules\Blog\app\Filament\Resources\BlogResource\Pages\ListBlogs;
use Modules\Blog\app\Filament\Resources\BlogResource\Pages\CreateBlog;
use Modules\Blog\app\Filament\Resources\BlogResource\Pages\EditBlog;
use Modules\Blog\app\Filament\Resources\BlogResource\Pages;
use Modules\Blog\app\Models\Blog;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Illuminate\Support\Str;

class BlogResource extends Resource
{
    protected static ?string $model = Blog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';



    public static function getNavigationLabel(): string
    {
        return __('admin.blogs');
    }

    public static function getModelLabel(): string
    {
        return __('admin.blog');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.blogs');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.blog'))->schema([
                    Select::make('category_id')
                        ->label(__('admin.blog_category'))
                        ->relationship('category', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    TextInput::make('title')
                        ->label(__('admin.blog_title'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, ?string $state, $record) {
                            $slug = Blog::generateUniqueSlug($state, $record?->id ?? 0);
                            $set('slug', $slug);
                        }),

                    TextInput::make('slug')
                        ->label(__('admin.blog_slug'))
                        ->required()
                        ->maxLength(255)
                        ->dehydrated()
                        ->afterStateUpdated(function (Set $set, ?string $state, $record) {
                            $set('slug', Blog::generateUniqueSlug($state, $record?->id ?? 0));
                        }),

                    SpatieMediaLibraryFileUpload::make('image')
                        ->label(__('admin.blog_image'))
                        ->collection('blogs')
                        ->image(),

                    RichEditor::make('content')
                        ->label(__('admin.blog_content'))
                        ->required()
                        ->columnSpanFull(),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->label(__('admin.blog_image'))
                    ->collection('blogs')
                    ->circular(),

                TextColumn::make('title')
                    ->label(__('admin.blog_title'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label(__('admin.blog_category'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('admin.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('admin.blog_category'))
                    ->relationship('category', 'name'),
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
            'index' => ListBlogs::route('/'),
            'create' => CreateBlog::route('/create'),
            'edit' => EditBlog::route('/{record}/edit'),
        ];
    }
}