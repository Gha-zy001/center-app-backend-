<?php

namespace Modules\Classroom\app\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\app\Models\AttendanceRecord;
use Modules\Classroom\app\Models\ClosedSession;
use Modules\Classroom\app\Models\Group;
use Modules\Classroom\app\Models\Student;

/**
 * Parents are not messaged yet (the Telegram bot is not wired up), so every response reports
 * "no parent notified".
 */
class AttendanceController extends ClassroomController
{
    public function session(Request $request): JsonResponse
    {
        $data = $request->validate($this->sessionRules());
        $group = Group::findOrFail($data['groupId']);

        $statuses = AttendanceRecord::where('group_id', $group->id)
            ->whereDate('date', $data['date'])
            ->pluck('status', 'student_id');

        return $this->successResponse('OK', [
            'date' => $data['date'],
            'groupId' => (string) $group->id,
            'closed' => ClosedSession::where('group_id', $group->id)->whereDate('date', $data['date'])->exists(),
            'students' => Student::where('group_id', $group->id)->orderBy('name')->orderBy('id')->get()
                ->map(fn (Student $student) => [
                    'id' => (string) $student->id,
                    'name' => $student->name,
                    'status' => $statuses[$student->id] ?? null,
                ]),
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate($this->sessionRules() + ['qrCode' => ['required', 'string']]);
        $group = Group::findOrFail($data['groupId']);
        $student = Student::where('qr_code', trim($data['qrCode']))->first();

        if (! $student) {
            return $this->fail('student_not_found', 404);
        }

        if ($student->group_id !== $group->id) {
            return $this->fail('student_not_in_group', 409);
        }

        $record = AttendanceRecord::firstOrNew([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'date' => $data['date'],
        ]);
        $alreadyRecorded = $record->exists && $record->status === AttendanceRecord::PRESENT;

        // A student marked absent by a closed session who then arrives becomes present.
        $record->status = AttendanceRecord::PRESENT;
        $record->save();

        return $this->successResponse('OK', [
            'student' => ['id' => (string) $student->id, 'name' => $student->name],
            'alreadyRecorded' => $alreadyRecorded,
            'parentNotified' => false,
        ]);
    }

    public function close(Request $request): JsonResponse
    {
        $data = $request->validate($this->sessionRules());
        $group = Group::findOrFail($data['groupId']);

        $recorded = AttendanceRecord::where('group_id', $group->id)->whereDate('date', $data['date'])->pluck('student_id');
        $unrecorded = Student::where('group_id', $group->id)->whereNotIn('id', $recorded)->get();

        foreach ($unrecorded as $student) {
            AttendanceRecord::create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'date' => $data['date'],
                'status' => AttendanceRecord::ABSENT,
            ]);
        }

        ClosedSession::firstOrCreate(['group_id' => $group->id, 'date' => $data['date']]);

        return $this->successResponse('OK', [
            'absentCount' => $unrecorded->count(),
            'parentsNotified' => 0,
            'parentsNotLinked' => $unrecorded->count(),
        ]);
    }

    // A day ahead of the server is allowed: the teacher's local day can lead UTC.
    private function sessionRules(): array
    {
        return [
            'groupId' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:tomorrow'],
        ];
    }
}
