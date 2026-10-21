<?php

namespace Modules\Teachers\app\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Teachers\app\Support\TeacherContext;

/**
 * Fails closed: with no teacher context the query matches nothing, so a forgotten
 * middleware can never expose another teacher's rows.
 */
class TeacherScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $teacherId = app(TeacherContext::class)->id();

        if ($teacherId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('teacher_id'), $teacherId);
    }
}
