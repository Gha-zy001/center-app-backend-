<?php

namespace Modules\User\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\User\app\Models\User;
// use Modules\User\Database\Factories\SocialAccountFactory;

class SocialAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id'
    ];

    // protected static function newFactory(): SocialAccountFactory
    // {
    //     // return SocialAccountFactory::new();
    // }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
