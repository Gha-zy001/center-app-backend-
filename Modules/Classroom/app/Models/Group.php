<?php

namespace Modules\Classroom\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Teachers\app\Concerns\BelongsToTeacher;

class Group extends Model
{
    use BelongsToTeacher;

    protected $table = 'student_groups';

    protected $fillable = ['name', 'days', 'time'];

    protected $casts = ['days' => 'array'];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
