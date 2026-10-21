<?php

namespace Modules\Classroom\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Teachers\app\Concerns\BelongsToTeacher;

class Student extends Model
{
    use BelongsToTeacher;

    protected $fillable = ['name', 'phone', 'parent_phone', 'group_id'];

    protected static function booted(): void
    {
        // Non-guessable on purpose: the QR code is what proves a student is at the door.
        static::creating(function (self $student) {
            $student->qr_code = Str::random(32);
            $student->telegram_token = Str::random(32);
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function telegramContacts(): HasMany
    {
        return $this->hasMany(TelegramContact::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
