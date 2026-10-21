<?php

namespace Modules\Classroom\app\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\app\Http\Requests\StudentRequest;
use Modules\Classroom\app\Models\AttendanceRecord;
use Modules\Classroom\app\Models\Exam;
use Modules\Classroom\app\Models\Group;
use Modules\Classroom\app\Models\Student;
use Modules\Classroom\app\Models\TelegramContact;
use Modules\Classroom\app\Services\TelegramClient;

class StudentController extends ClassroomController
{
    public function __construct(private readonly TelegramClient $telegram) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $pageSize = $this->perPage($request, 'pageSize', 20);

        $page = Student::with('group')
            ->when($request->query('groupId'), fn ($query, $groupId) => $query->where('group_id', $groupId))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

                $query->where(function ($inner) use ($like) {
                    foreach (['name', 'phone', 'parent_phone'] as $column) {
                        $inner->orWhereRaw("LOWER($column) LIKE ? ESCAPE '\\'", [$like]);
                    }
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($pageSize, page: max(1, (int) $request->query('page', 1)));

        return $this->successResponse('OK', [
            'items' => collect($page->items())->map(fn (Student $student) => $this->present($student)),
            'total' => $page->total(),
        ]);
    }

    public function store(StudentRequest $request): JsonResponse
    {
        $group = Group::find($request->validated('groupId'));

        if (! $group) {
            return $this->fail('group_not_found', 404);
        }

        $student = Student::create($this->attributes($request, $group))->load('group');

        return $this->successResponse('OK', ['student' => $this->presentDetails($student)], 201);
    }

    public function show(int $id): JsonResponse
    {
        $student = Student::with('group')->findOrFail($id);

        return $this->successResponse('OK', ['student' => $this->presentDetails($student)]);
    }

    public function update(StudentRequest $request, int $id): JsonResponse
    {
        $student = Student::findOrFail($id);
        $group = Group::find($request->validated('groupId'));

        if (! $group) {
            return $this->fail('group_not_found', 404);
        }

        $student->update($this->attributes($request, $group));

        return $this->successResponse('OK', ['student' => $this->presentDetails($student->load('group'))]);
    }

    public function destroy(int $id): JsonResponse
    {
        // Attendance and grades are removed with the student (cascade).
        Student::findOrFail($id)->delete();

        return $this->successResponse('OK');
    }

    public function attendance(int $id): JsonResponse
    {
        $student = Student::findOrFail($id);

        $items = AttendanceRecord::with('group')
            ->where('student_id', $student->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse('OK', [
            'items' => $items->map(fn (AttendanceRecord $record) => [
                'date' => $record->date->toDateString(),
                'status' => $record->status,
                'groupName' => $record->group?->name ?? '',
            ]),
            'presentCount' => $items->where('status', AttendanceRecord::PRESENT)->count(),
            'absentCount' => $items->where('status', AttendanceRecord::ABSENT)->count(),
        ]);
    }

    // Exams of the student's current group, plus any exam they already have a grade for (history is kept).
    public function grades(int $id): JsonResponse
    {
        $student = Student::findOrFail($id);
        $scores = $student->grades()->pluck('score', 'exam_id');

        $exams = Exam::where('group_id', $student->group_id)
            ->orWhereIn('id', $scores->keys())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse('OK', [
            'items' => $exams->map(fn (Exam $exam) => [
                'examId' => (string) $exam->id,
                'examName' => $exam->name,
                'maxScore' => $exam->max_score,
                'score' => isset($scores[$exam->id]) ? (float) $scores[$exam->id] : null,
                'date' => $exam->date->toDateString(),
            ]),
        ]);
    }

    private function attributes(StudentRequest $request, Group $group): array
    {
        return [
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone') ?? '',
            'parent_phone' => $request->validated('parentPhone') ?? '',
            'group_id' => $group->id,
        ];
    }

    private function present(Student $student): array
    {
        return [
            'id' => (string) $student->id,
            'name' => $student->name,
            'phone' => $student->phone,
            'parentPhone' => $student->parent_phone,
            'groupId' => (string) $student->group_id,
            'groupName' => $student->group?->name ?? '',
        ];
    }

    private function presentDetails(Student $student): array
    {
        return $this->present($student) + [
            'qrCode' => $student->qr_code,
            // One link for every parent: they press Start and prove the number by sharing it.
            'telegramLink' => $this->telegram->botLink(),
            'telegramContacts' => $student->telegramContacts()->orderBy('id')->get()->map(fn (TelegramContact $contact) => [
                'id' => (string) $contact->id,
                'name' => $contact->name,
                'linkedAt' => $contact->created_at->toIso8601String(),
            ]),
        ];
    }
}
