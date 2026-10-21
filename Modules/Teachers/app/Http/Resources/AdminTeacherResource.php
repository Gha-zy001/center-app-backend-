<?php

namespace Modules\Teachers\app\Http\Resources;

use Illuminate\Http\Request;

/**
 * A teacher as the center admin sees them: the account plus how much the teacher uses it.
 * The counts come from relations the Classroom module adds to Teacher (see ClassroomServiceProvider).
 */
class AdminTeacherResource extends TeacherResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            // The subscription starts the moment the admin creates the account.
            'subscriptionStartedAt' => $this->created_at?->toIso8601String(),
            'studentCount' => $this->students_count ?? $this->students()->count(),
            'groupCount' => $this->groups_count ?? $this->groups()->count(),
        ];
    }
}
