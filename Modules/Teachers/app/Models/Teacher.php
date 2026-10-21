<?php

namespace Modules\Teachers\app\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A teacher is a tenant of the center: every teacher-owned row carries its teacher_id
 * (see BelongsToTeacher). Teachers live in their own table and authenticate through the
 * "sanctum-teacher" guard only, never as admins or regular users.
 */
class Teacher extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'username',
        'password',
        'phone',
        'status',
        'last_login_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = strtolower(trim($value));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
