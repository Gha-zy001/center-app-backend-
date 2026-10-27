<?php

namespace Modules\Teachers\app\Contracts;

use Modules\Teachers\app\Models\Teacher;

/**
 * Lets another module (subscriptions) refuse a teacher whose account is otherwise active, and
 * warn one who is about to lose access. The Teachers module only asks; nothing is enforced or
 * shown when no module binds an implementation.
 */
interface TeacherAccessGate
{
    /**
     * @return string|null a translation key explaining the refusal, or null when access is allowed
     */
    public function denial(Teacher $teacher): ?string;

    /**
     * A heads-up for a teacher who still has access, e.g. the package ends soon.
     *
     * @return array{code: string, message: string, endsAt: string, hoursLeft: int}|null
     */
    public function notice(Teacher $teacher): ?array;
}
