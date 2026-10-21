<?php

namespace Modules\Classroom\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Teachers\app\Concerns\BelongsToTeacher;

class Exam extends Model
{
    use BelongsToTeacher;

    protected $fillable = ['group_id', 'name', 'max_score', 'date'];

    protected $casts = ['max_score' => 'float', 'date' => 'date:Y-m-d'];

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
