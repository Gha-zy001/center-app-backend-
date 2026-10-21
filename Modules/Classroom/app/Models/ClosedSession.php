<?php

namespace Modules\Classroom\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Teachers\app\Concerns\BelongsToTeacher;

class ClosedSession extends Model
{
    use BelongsToTeacher;

    protected $fillable = ['group_id', 'date'];

    protected $casts = ['date' => 'date:Y-m-d'];
}
