<?php

namespace Modules\Teachers\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Support\Query\BaseQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Teachers\app\Http\Requests\StoreTeacherRequest;
use Modules\Teachers\app\Http\Requests\UpdateTeacherPasswordRequest;
use Modules\Teachers\app\Http\Requests\UpdateTeacherRequest;
use Modules\Teachers\app\Http\Resources\AdminTeacherResource;
use Modules\Teachers\app\Models\Teacher;

/**
 * How the center admin manages teachers. Any change that must lock a teacher out (suspend,
 * password reset, delete) also revokes their tokens, so it applies immediately.
 */
class AdminTeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teachers = (new BaseQueryService(Teacher::query(), $request))
            ->allowedFilters(['status'])
            ->allowedSorts(['id', 'name', 'username', 'created_at', 'last_login_at'])
            ->allowedSearch(['name', 'username', 'phone'])
            ->apply()
            ->paginate($this->perPage($request));

        return $this->paginated($teachers, AdminTeacherResource::class, __('messages.teachers_fetched_successfully'));
    }

    public function show(Teacher $teacher): JsonResponse
    {
        return $this->successResponse(__('messages.teacher_fetched_successfully'), new AdminTeacherResource($teacher));
    }

    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $data = $request->validated();
        $temporaryPassword = null;

        if (empty($data['password'])) {
            $data['password'] = $temporaryPassword = Str::password(12, symbols: false);
        }

        $teacher = Teacher::create($data);

        return $this->successResponse(__('messages.teacher_created_successfully'), [
            'teacher' => new AdminTeacherResource($teacher),
            // Shown once, only when the password was generated here.
            'temporaryPassword' => $temporaryPassword,
        ], 201);
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher): JsonResponse
    {
        $teacher->update($request->validated());

        return $this->successResponse(__('messages.teacher_updated_successfully'), new AdminTeacherResource($teacher));
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        $teacher->tokens()->delete();
        $teacher->delete();

        return $this->successResponse(__('messages.teacher_deleted_successfully'));
    }

    public function activate(Teacher $teacher): JsonResponse
    {
        $teacher->update(['status' => Teacher::STATUS_ACTIVE]);

        return $this->successResponse(__('messages.teacher_activated_successfully'), new AdminTeacherResource($teacher));
    }

    public function deactivate(Teacher $teacher): JsonResponse
    {
        $teacher->update(['status' => Teacher::STATUS_SUSPENDED]);
        $teacher->tokens()->delete();

        return $this->successResponse(__('messages.teacher_deactivated_successfully'), new AdminTeacherResource($teacher));
    }

    public function updatePassword(UpdateTeacherPasswordRequest $request, Teacher $teacher): JsonResponse
    {
        $teacher->update(['password' => $request->validated('password')]);
        $teacher->tokens()->delete();

        return $this->successResponse(__('messages.teacher_password_updated_successfully'));
    }
}
