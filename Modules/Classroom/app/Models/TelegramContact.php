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

    // teacher_bot_id: the teacher's own bot this parent talks to (null = the shared platform bot).
    protected $fillable = ['student_id', 'chat_id', 'name', 'teacher_bot_id'];
}
