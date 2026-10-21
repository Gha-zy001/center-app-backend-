<?php

namespace Modules\User\app\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Modules\User\app\Filament\Resources\UserResource\Pages\ListUsers;
use Modules\User\app\Filament\Resources\UserResource\Pages\CreateUser;
use Modules\User\app\Filament\Resources\UserResource\Pages\EditUser;
use Modules\User\app\Filament\Resources\UserResource\Pages;
use Modules\User\app\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return __('admin.users');
    }





    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.user_details'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->label(__('admin.keys.avatar'))
                            ->collection('avatar')
                            ->image()
                            ->avatar()
                            ->alignCenter()
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label(__('admin.keys.name'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('email')
                                ->label(__('admin.keys.email'))
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),

                            TextInput::make('phone')
                                ->label(__('admin.keys.phone'))
                                ->tel()
                                ->maxLength(20),

                            Select::make('status')
                                ->label(__('admin.keys.status'))
                                ->options([
                                    'active' => __('admin.status.active'),
                                    'pending' => __('admin.status.pending'),
                                    'rejected' => __('admin.status.rejected'),
                                    'suspended' => __('admin.status.suspended'),
                                    'banned' => __('admin.status.banned'),
                                ])
                                ->required()
                                ->native(false),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('password')
                                ->label(__('admin.keys.password'))
                                ->password()
                                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                ->dehydrated(fn ($state) => filled($state))
                                ->required(fn (string $context): bool => $context === 'create'),

                            Select::make('roles')
                                ->label(__('admin.keys.roles'))
                                ->relationship('roles', 'name', modifyQueryUsing: fn ($query) => $query->where('guard_name', 'web'))
                                ->multiple()
                                ->preload()
                                ->searchable(),

                            // Extra permissions on top of the ones the user's roles already grant.
                            Select::make('permissions')
                                ->label(__('admin.direct_permissions'))
                                ->relationship('permissions', 'name', modifyQueryUsing: fn ($query) => $query->where('guard_name', 'web'))
                                ->multiple()
                                ->preload()
                                ->searchable(),
                        ]),
                    ]),

                Section::make(__('admin.preferences'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('locale')
                                ->label(__('admin.keys.locale')),
                            TextInput::make('timezone')
                                ->label(__('admin.keys.timezone')),
                        ]),
                    ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('avatar')
                    ->label(__('admin.keys.avatar'))
                    ->collection('avatar')
                    ->conversion('thumb')
                    ->circular(),

                TextColumn::make('name')
                    ->label(__('admin.keys.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('admin.keys.email'))
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('admin.keys.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'rejected', 'suspended', 'banned' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => __("admin.status.{$state}")),

                TextColumn::make('roles.name')
                    ->label(__('admin.keys.roles'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label(__('admin.keys.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}