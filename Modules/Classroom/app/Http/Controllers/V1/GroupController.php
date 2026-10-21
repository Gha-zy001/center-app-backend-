<?php

namespace Modules\Classroom\app\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Modules\Classroom\app\Http\Requests\GroupRequest;
use Modules\Classroom\app\Models\Group;

class GroupController extends ClassroomController
{
    public function index(): JsonResponse
    {
        $groups = Group::withCount('students')->orderBy('name')->get();

        return $this->successResponse('OK', ['items' => $groups->map(fn (Group $group) => $this->present($group))]);
    }

    public function store(GroupRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($this->nameTaken($data['name'])) {
            return $this->fail('group_name_taken', 409);
        }

        $group = Group::create($data)->loadCount('students');

        return $this->successResponse('OK', ['group' => $this->present($group)], 201);
    }

    public function update(GroupRequest $request, int $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $data = $request->validated();

        if ($this->nameTaken($data['name'], $group->id)) {
            return $this->fail('group_name_taken', 409);
        }

        $group->update($data);

        return $this->successResponse('OK', ['group' => $this->present($group->loadCount('students'))]);
    }

    public function destroy(int $id): JsonResponse
    {
        $group = Group::findOrFail($id);

        if ($group->students()->exists()) {
            return $this->fail('group_has_students', 409);
        }

        // Exams, attendance and closed sessions go with the group (cascade).
        $group->delete();

        return $this->successResponse('OK');
    }

    // Names are unique per teacher, case-insensitively (the scope already limits to this teacher).
    private function nameTaken(string $name, ?int $ignoreId = null): bool
    {
        return Group::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function present(Group $group): array
    {
        return [
            'id' => (string) $group->id,
            'name' => $group->name,
            'days' => $group->days,
            'time' => $group->time,
            'studentCount' => $group->students_count,
        ];
    }
}
