<?php

namespace Modules\User\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
// use Modules\User\Database\Factories\RefreshTokenFactory;

class RefreshToken extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['user_id', 'token', 'expires_at', 'is_revoked'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function createToken(User $user): string
    {
        $token = Str::random(64);
        
        static::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
        ]);
        
        return $token;
    }

    public static function findValidToken(string $token): ?self
    {
        return static::where('token', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->where('is_revoked', false)
            ->first();
    }

    /**
     * Atomically revoke a valid token so concurrent requests cannot reuse it.
     */
    public static function consume(string $token): ?self
    {
        $hash = hash('sha256', $token);

        $claimed = static::where('token', $hash)
            ->where('expires_at', '>', now())
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);

        return $claimed === 1 ? static::where('token', $hash)->first() : null;
    }

    public static function revokeAllFor(User $user): void
    {
        static::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);
    }


}
