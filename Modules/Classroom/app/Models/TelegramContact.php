<?php

namespace Modules\Classroom\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Teachers\app\Concerns\BelongsToTeacher;

/**
 * A Telegram account that proved (by sharing its own phone number) it belongs to a student's parent.
 */
class TelegramContact extends Model
{
    use BelongsToTeacher;

    protected $table = 'student_telegram_contacts';

    protected $fillable = ['student_id', 'chat_id', 'name'];
}
