<?php

namespace Modules\Authorization\app\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Back-office accounts. They live in their own table and authenticate through the
 * "admin" (panel) and "sanctum-admin" (API) guards, never as regular users.
 */
class Admin extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasRoles, Notifiable;

    /**
     * Spatie permission guard: admin roles and permissions are stored with this guard name.
     */
    protected string $guard_name = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The single role that bypasses every permission check.
     */
    public const SUPER_ADMIN_ROLE = 'super-admin';

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN_ROLE);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active === true;
    }
}
