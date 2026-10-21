<?php

namespace Modules\User\app\Filament\Resources;

use Filament\Actions\DeleteAction;
use Illuminate\Auth\Access\Response;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Modules\Authorization\app\Services\UserRoleService;
use Modules\User\app\Filament\Resources\UserRoleResource\Pages\ManageUserRoles;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use UnitEnum;

/**
 * Roles for regular users (web guard). Admin roles are a separate set and never appear here.
 */
class UserRoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('admin.user_roles');
    }

    public static function getModelLabel(): string
    {
        return __('admin.user_role');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.user_roles');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('guard_name', UserRoleService::GUARD)
            ->withCount(['permissions', 'users']);
    }

    /**
     * Everything on this screen is behind "manage-user-roles"; the default role can't be deleted.
     */
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        if (! auth('admin')->user()?->can('manage-user-roles')) {
            return Response::deny();
        }

        if ($record && in_array($action, ['delete', 'forceDelete'], true) && self::isDefault($record)) {
            return Response::deny(__('messages.default_user_role_locked'));
        }

        return Response::allow();
    }

    public static function isDefault(?Model $record): bool
    {
        return $record?->name === UserRoleService::DEFAULT_ROLE;
    }

    public static function forgetPermissionCache(): void
    {
        // The permissions relationship is saved directly, bypassing Spatie's own cache reset.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.role_name'))
                    ->required()
                    ->maxLength(100)
                    ->regex('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/')
                    ->validationMessages(['regex' => __('messages.role_name_format')])
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('guard_name', UserRoleService::GUARD),
                    )
                    ->disabled(fn (?Model $record) => self::isDefault($record))
                    ->helperText(fn (?Model $record) => self::isDefault($record) ? __('messages.default_user_role_locked') : null),

                CheckboxList::make('permissions')
                    ->label(__('admin.permissions'))
                    ->relationship(
                        'permissions',
                        'name',
                        fn (Builder $query) => $query->where('guard_name', UserRoleService::GUARD)->orderBy('name'),
                    )
                    ->columns(3)
                    ->searchable()
                    ->bulkToggleable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.role_name'))
                    ->badge()
                    ->color(fn (Model $record) => self::isDefault($record) ? 'gray' : 'primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('permissions_count')
                    ->label(__('admin.permissions'))
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label(__('admin.users'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()->after(fn () => self::forgetPermissionCache()),
                DeleteAction::make()->after(fn () => self::forgetPermissionCache()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserRoles::route('/'),
        ];
    }
}
