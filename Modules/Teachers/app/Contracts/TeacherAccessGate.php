<?php

namespace Modules\Teachers\app\Contracts;

use Modules\Teachers\app\Models\Teacher;

/**
 * Lets another module (subscriptions) refuse a teacher whose account is otherwise active.
 * The Teachers module only asks; nothing is enforced when no module binds an implementation.
 */
interface TeacherAccessGate
{
    /**
     * @return string|null a translation key explaining the refusal, or null when access is allowed
     */
    public function denial(Teacher $teacher): ?string;
}
