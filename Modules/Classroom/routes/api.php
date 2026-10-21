<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\Classroom\app\Http\Controllers\V1\AttendanceController;
use Modules\Classroom\app\Http\Controllers\V1\ExamController;
use Modules\Classroom\app\Http\Controllers\V1\GroupController;
use Modules\Classroom\app\Http\Controllers\V1\StudentController;
use Modules\Teachers\app\Http\Middleware\ResolveTeacherContext;

// Everything a teacher manages. ResolveTeacherContext scopes every query to the signed-in teacher.
Route::prefix('v1/teacher')
    ->middleware([SetLocale::class, 'auth:sanctum-teacher', ResolveTeacherContext::class, 'rate_limit:user'])
    ->group(function () {
        Route::get('groups', [GroupController::class, 'index']);
        Route::post('groups', [GroupController::class, 'store']);
        Route::put('groups/{id}', [GroupController::class, 'update'])->whereNumber('id');
        Route::delete('groups/{id}', [GroupController::class, 'destroy'])->whereNumber('id');

        Route::get('students', [StudentController::class, 'index']);
        Route::post('students', [StudentController::class, 'store']);
        Route::get('students/{id}', [StudentController::class, 'show'])->whereNumber('id');
        Route::put('students/{id}', [StudentController::class, 'update'])->whereNumber('id');
        Route::delete('students/{id}', [StudentController::class, 'destroy'])->whereNumber('id');
        Route::get('students/{id}/attendance', [StudentController::class, 'attendance'])->whereNumber('id');
        Route::get('students/{id}/grades', [StudentController::class, 'grades'])->whereNumber('id');

        Route::get('attendance/session', [AttendanceController::class, 'session']);
        Route::post('attendance/scan', [AttendanceController::class, 'scan']);
        Route::post('attendance/close', [AttendanceController::class, 'close']);

        Route::get('exams', [ExamController::class, 'index']);
        Route::post('exams', [ExamController::class, 'store']);
        Route::patch('exams/{id}', [ExamController::class, 'update'])->whereNumber('id');
        Route::delete('exams/{id}', [ExamController::class, 'destroy'])->whereNumber('id');
        Route::get('exams/{id}/grades', [ExamController::class, 'grades'])->whereNumber('id');
        Route::put('exams/{examId}/grades/{studentId}', [ExamController::class, 'setGrade'])->whereNumber(['examId', 'studentId']);
    });
