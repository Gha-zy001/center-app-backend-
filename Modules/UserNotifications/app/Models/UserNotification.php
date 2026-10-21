<?php

namespace Modules\UserNotifications\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\User\app\Models\User;
// use Modules\UserNotifications\Database\Factories\UserNotificationFactory;

class UserNotification extends Model
{
    use HasFactory , SoftDeletes;


    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'read_at',
    ];

    protected $dates = ['read_at', 'deleted_at'];
    // protected static function newFactory(): UserNotificationFactory
    // {
    //     // return UserNotificationFactory::new();
    // }


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
