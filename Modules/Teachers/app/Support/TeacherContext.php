<?php

namespace Modules\Teachers\app\Support;

use Closure;
use Modules\Teachers\app\Models\Teacher;

/**
 * The teacher the current request (or job) acts for. Isolation between teachers is enforced
 * by the BelongsToTeacher global scope reading this id, so the id may only ever come from
 * the authenticated teacher or from trusted server code, never from client input.
 */
class TeacherContext
{
    private ?int $id = null;

    public function id(): ?int
    {
        return $this->id;
    }

    public function isSet(): bool
    {
        return $this->id !== null;
    }

    public function set(Teacher|int $teacher): void
    {
        $this->id = $teacher instanceof Teacher ? $teacher->getKey() : $teacher;
    }

    public function clear(): void
    {
        $this->id = null;
    }

    /**
     * Run a callback as the given teacher, then restore the previous context even if it throws.
     */
    public function runFor(Teacher|int $teacher, Closure $callback): mixed
    {
        $previous = $this->id;

        try {
            $this->set($teacher);

            return $callback();
        } finally {
            $this->id = $previous;
        }
    }
}
