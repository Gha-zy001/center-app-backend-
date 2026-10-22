<?php

namespace Modules\Classroom\app\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Classroom\app\Models\AttendanceRecord;
use Modules\Classroom\app\Models\ClosedSession;
use Modules\Classroom\app\Models\Group;
use Modules\Classroom\app\Models\Student;
use Modules\Classroom\app\Services\ParentNotifier;

class AttendanceController extends ClassroomController
{
    public function __construct(private readonly ParentNotifier $notifier) {}

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

        // Parents hear about a student once: scanning the same card again says nothing.
        $student->setRelation('group', $group);
        $notified = ! $alreadyRecorded && $this->notifier->present($student, $data['date']);

        return $this->successResponse('OK', [
            'student' => ['id' => (string) $student->id, 'name' => $student->name],
            'alreadyRecorded' => $alreadyRecorded,
            'parentNotified' => $notified,
        ]);
    }

    /**
     * The teacher's 3-way toggle: present, absent, or null (not recorded = no row). Allowed on
     * closed sessions too (the correction path) and never opens or closes a session.
     *
     * Parents are told once per real change to present or absent, the same texts as scan and close.
     * Going to null, or repeating a status, sends nothing. Every real transition notifies (no
     * debounce); messages are sent in request order, so the last one always matches the stored state.
     */
    public function setStatus(Request $request): JsonResponse
    {
        // The raw body: the framework turns "" into null, and "" is an invalid status here.
        $input = json_decode($request->getContent(), true);
        $input = is_array($input) ? $input : [];
        $validator = Validator::make($input, [
            'groupId' => ['required', 'string', 'regex:/^\d{1,18}$/'],
            'studentId' => ['required', 'string', 'regex:/^\d{1,18}$/'],
            'date' => ['required', 'string', 'date_format:Y-m-d', 'before_or_equal:tomorrow'],
        ]);

        // `status` must be in the body but may be null, which Laravel's `required` rejects.
        $statusOk = array_key_exists('status', $input)
            && ($input['status'] === null || in_array($input['status'], [AttendanceRecord::PRESENT, AttendanceRecord::ABSENT], true));

        if ($validator->fails() || ! $statusOk) {
            return $this->fail('invalid_request', 400);
        }

        $group = Group::find($input['groupId']);
        $student = $group ? Student::where('group_id', $group->id)->find($input['studentId']) : null;

        if (! $group || ! $student) {
            return $this->fail('student_not_found', 404);
        }

        $status = $input['status'];
        $date = $input['date'];

        $previous = DB::transaction(function () use ($student, $group, $date, $status) {
            $find = fn () => AttendanceRecord::where('student_id', $student->id)
                ->where('group_id', $group->id)->whereDate('date', $date)->lockForUpdate()->first();
            $record = $find();
            $before = $record?->status;

            if ($status === null) {
                $record?->delete();
            } elseif ($record) {
                $record->update(['status' => $status]);
            } else {
                try {
                    // A savepoint, so losing a race on the unique key doesn't poison the transaction.
                    DB::transaction(fn () => AttendanceRecord::create([
                        'student_id' => $student->id, 'group_id' => $group->id, 'date' => $date, 'status' => $status,
                    ]));
                } catch (UniqueConstraintViolationException) {
                    $record = $find();
                    $before = $record?->status;
                    $record?->update(['status' => $status]);
                }
            }

            return $before;
        });

        // After commit, and a Telegram failure never touches the saved status (the client never throws).
        if ($status !== null && $status !== $previous) {
            $student->setRelation('group', $group);
            $notified = $status === AttendanceRecord::PRESENT
                ? $this->notifier->present($student, $date)
                : $this->notifier->absent($student, $date);

            if ($notified) {
                Log::info('attendance parent notified', ['student' => $student->id, 'group' => $group->id, 'status' => $status]);
            }
        }

        return $this->successResponse('OK', ['studentId' => (string) $student->id, 'status' => $status]);
    }

    public function close(Request $request): JsonResponse
    {
        $data = $request->validate($this->sessionRules());
        $group = Group::findOrFail($data['groupId']);

        $recorded = AttendanceRecord::where('group_id', $group->id)->whereDate('date', $data['date'])->pluck('student_id');
        $unrecorded = Student::where('group_id', $group->id)->whereNotIn('id', $recorded)->get();

        $notified = 0;

        foreach ($unrecorded as $student) {
            AttendanceRecord::create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'date' => $data['date'],
                'status' => AttendanceRecord::ABSENT,
            ]);

            $student->setRelation('group', $group);
            $notified += (int) $this->notifier->absent($student, $data['date']);
        }

        ClosedSession::firstOrCreate(['group_id' => $group->id, 'date' => $data['date']]);

        return $this->successResponse('OK', [
            'absentCount' => $unrecorded->count(),
            'parentsNotified' => $notified,
            'parentsNotLinked' => $unrecorded->count() - $notified,
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
