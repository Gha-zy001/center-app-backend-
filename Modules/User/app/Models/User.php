<?php

namespace Modules\User\app\Models;

use App\Core\Traits\HasStandardMedia;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Modules\User\app\Models\SocialAccount;
use Modules\UserNotifications\app\Models\UserFcmToken;
use Modules\UserNotifications\app\Models\UserNotification;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable implements HasMedia, FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasRoles, InteractsWithMedia;

    use LogsActivity;


    

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at', 
        'uuid',
        'phone',
        'status', 
        'last_login_at',
        'locale',
        'timezone', 
        'preferences',
        'deleted_at',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('User');
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($user) {
            $user->uuid = (string) Str::uuid();
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function (?Media $media = null) {
                $this->addMediaConversion('thumb')
                    ->width(200)
                    ->height(200)
                    ->sharpen(10);
            });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
    
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function otpCodes()
    {
        return $this->hasMany(OtpCode::class, 'email', 'email');
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function fcmTokens()
    {
        return $this->hasMany(UserFcmToken::class);
    }

    public function userNotifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Back-office access belongs to the separate admins table; regular users never get in.
        return false;
    }
}