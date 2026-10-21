<?php

namespace Modules\Classroom\app\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\app\Models\Exam;
use Modules\Classroom\app\Models\Grade;
use Modules\Classroom\app\Models\Group;
use Modules\Classroom\app\Models\Student;
use Modules\Classroom\app\Services\ParentNotifier;

class ExamController extends ClassroomController
{
    public function __construct(private readonly ParentNotifier $notifier) {}

    public function index(Request $request): JsonResponse
    {
        $groupId = $request->query('groupId');

        if (! $groupId) {
            return $this->fail('group_required', 400);
        }

        $group = Group::findOrFail($groupId);
        $exams = Exam::where('group_id', $group->id)->orderByDesc('date')->orderByDesc('id')->get();

        return $this->successResponse('OK', ['items' => $exams->map(fn (Exam $exam) => $this->present($exam))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'groupId' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'maxScore' => ['required', 'numeric', 'gt:0', 'lte:1000', 'decimal:0,2'],
        ]);
        $group = Group::findOrFail($data['groupId']);

        $exam = Exam::create([
            'group_id' => $group->id,
            'name' => trim(preg_replace('/\s+/u', ' ', $data['name'])),
            'max_score' => $data['maxScore'],
            'date' => now()->toDateString(),
        ]);

        return $this->successResponse('OK', ['exam' => $this->present($exam)], 201);
    }

    // Only the name can change: the max score is locked once grades may exist.
    public function update(Request $request, int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        $exam->update(['name' => trim(preg_replace('/\s+/u', ' ', $data['name']))]);

        return $this->successResponse('OK', ['exam' => $this->present($exam)]);
    }

    public function destroy(int $id): JsonResponse
    {
        Exam::findOrFail($id)->delete();

        return $this->successResponse('OK');
    }

    public function grades(int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $scores = Grade::where('exam_id', $exam->id)->pluck('score', 'student_id');

        $items = Student::where('group_id', $exam->group_id)->orderBy('name')->orderBy('id')->get()
            ->map(fn (Student $student) => [
                'studentId' => (string) $student->id,
                'studentName' => $student->name,
                'score' => isset($scores[$student->id]) ? (float) $scores[$student->id] : null,
            ]);

        return $this->successResponse('OK', ['exam' => $this->present($exam), 'items' => $items]);
    }

    public function setGrade(Request $request, int $examId, int $studentId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $student = Student::where('group_id', $exam->group_id)->findOrFail($studentId);

        $data = $request->validate(['score' => ['present', 'nullable', 'numeric', 'decimal:0,2']]);
        $score = $data['score'];

        if ($score !== null && ($score < 0 || $score > $exam->max_score)) {
            return $this->fail('score_out_of_range', 400);
        }

        if ($score === null) {
            Grade::where('exam_id', $exam->id)->where('student_id', $student->id)->delete();
        } else {
            $grade = Grade::updateOrCreate(['exam_id' => $exam->id, 'student_id' => $student->id], ['score' => $score]);

            // Only a real change reaches the parent, not a re-save of the same score.
            if ($grade->wasRecentlyCreated || $grade->wasChanged('score')) {
                $this->notifier->grade($student, $exam, (float) $score);
            }
        }

        return $this->successResponse('OK', ['studentId' => (string) $student->id, 'score' => $score === null ? null : (float) $score]);
    }

    private function present(Exam $exam): array
    {
        return [
            'id' => (string) $exam->id,
            'groupId' => (string) $exam->group_id,
            'name' => $exam->name,
            'maxScore' => $exam->max_score,
            'date' => $exam->date->toDateString(),
        ];
    }
}
