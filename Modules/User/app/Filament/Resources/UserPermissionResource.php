<?php

namespace Modules\User\app\Filament\Resources;

use Filament\Actions\DeleteAction;
use Illuminate\Auth\Access\Response;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Modules\Authorization\app\Services\UserRoleService;
use Modules\User\app\Filament\Resources\UserPermissionResource\Pages\ManageUserPermissions;
use Spatie\Permission\Models\Permission;
use UnitEnum;

/**
 * The catalogue of permissions that can be granted to regular users (web guard).
 * Permissions are created and deleted but never renamed, because code checks them by name.
 */
class UserPermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 21;

    public static function getNavigationLabel(): string
    {
        return __('admin.user_permissions');
    }

    public static function getModelLabel(): string
    {
        return __('admin.user_permission');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.user_permissions');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('guard_name', UserRoleService::GUARD)
            ->withCount('roles');
    }

    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        if (! auth('admin')->user()?->can('manage-user-roles')) {
            return Response::deny();
        }

        // Renaming would silently break every check that uses the old name.
        return $action === 'update' ? Response::deny() : Response::allow();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.permission_name'))
                    ->required()
                    ->maxLength(100)
                    ->regex('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/')
                    ->validationMessages(['regex' => __('messages.permission_name_format')])
                    ->unique(modifyRuleUsing: fn (Unique $rule) => $rule->where('guard_name', UserRoleService::GUARD))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.permission_name'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('roles_count')
                    ->label(__('admin.user_roles'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                DeleteAction::make()->after(fn () => UserRoleResource::forgetPermissionCache()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserPermissions::route('/'),
        ];
    }
}
