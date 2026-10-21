<?php

namespace Modules\User\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\User\Database\Factories\OtpCodeFactory;

class OtpCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['email', 'code', 'type', 'expires_at'];

    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }
}
