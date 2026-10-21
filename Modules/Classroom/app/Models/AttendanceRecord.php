<?php

namespace Modules\Classroom\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Teachers\app\Concerns\BelongsToTeacher;

class AttendanceRecord extends Model
{
    use BelongsToTeacher;

    public const PRESENT = 'present';

    public const ABSENT = 'absent';

    protected $fillable = ['student_id', 'group_id', 'date', 'status'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
