<?php

namespace Modules\Classroom\app\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherBotRequest extends Model
{
    protected $fillable = ['teacher_id', 'code', 'telegram_user_id', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
