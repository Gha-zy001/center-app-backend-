<?php

namespace Modules\Classroom\app\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A teacher's own Telegram bot. Looked up by webhook key outside any teacher request, so it
 * deliberately has no teacher scope. The token never leaves the server.
 */
class TeacherBot extends Model
{
    protected $fillable = ['teacher_id', 'telegram_bot_id', 'username', 'token', 'webhook_key', 'webhook_secret'];

    protected $hidden = ['token', 'webhook_secret', 'webhook_key'];

    protected function casts(): array
    {
        return ['token' => 'encrypted'];
    }

    public function link(): string
    {
        return 'https://t.me/'.$this->username;
    }
}
