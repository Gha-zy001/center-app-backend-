<?php

namespace Modules\UserNotifications\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\User\app\Models\User;
// use Modules\UserNotifications\Database\Factories\UserFcmTokenFactory;

class UserFcmToken extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'device_token',
        'device_type',
    ];

    // protected static function newFactory(): UserFcmTokenFactory
    // {
    //     // return UserFcmTokenFactory::new();
    // }


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
