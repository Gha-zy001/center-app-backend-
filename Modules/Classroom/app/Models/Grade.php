<?php

namespace Modules\Classroom\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Teachers\app\Concerns\BelongsToTeacher;

class Grade extends Model
{
    use BelongsToTeacher;

    protected $fillable = ['exam_id', 'student_id', 'score'];

    protected $casts = ['score' => 'float'];
}
