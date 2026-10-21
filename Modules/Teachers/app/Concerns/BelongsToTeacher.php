<?php

namespace Modules\Teachers\app\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Teachers\app\Models\Teacher;
use Modules\Teachers\app\Scopes\TeacherScope;
use Modules\Teachers\app\Support\TeacherContext;

/**
 * Put on every teacher-owned model (its table needs a teacher_id column). Reads are limited
 * to the current teacher and new rows are stamped with the current teacher.
 */
trait BelongsToTeacher
{
    public static function bootBelongsToTeacher(): void
    {
        static::addGlobalScope(new TeacherScope);

        static::creating(function ($model) {
            if ($model->teacher_id !== null) {
                return;
            }

            $teacherId = app(TeacherContext::class)->id();

            if ($teacherId === null) {
                throw new LogicException('Cannot create '.static::class.' without a teacher context.');
            }

            $model->teacher_id = $teacherId;
        });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withoutGlobalScopes();
    }
}
