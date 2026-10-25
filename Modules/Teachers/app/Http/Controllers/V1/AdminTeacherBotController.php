<?php

namespace Modules\Teachers\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Classroom\app\Models\TeacherBot;
use Modules\Teachers\app\Models\Teacher;

/**
 * What the admin's "Telegram bot" screen shows for a teacher. Every teacher's parents use the
 * one shared bot (configured in .env), so there is nothing to create or rename per teacher:
 * the screen sees that bot as already active, and any attempt to change it is refused.
 */
class AdminTeacherBotController extends Controller
{
    public function show(Teacher $teacher): JsonResponse
    {
        // The teacher's own bot when they made one, otherwise the shared platform bot.
        $own = TeacherBot::where('teacher_id', $teacher->id)->value('username');
        $username = $own ?: config('services.telegram.bot_username');

        return $this->successResponse('OK', [
            'bot' => $username ? ['name' => $username, 'username' => $username, 'status' => 'active'] : null,
            // Bots are not created per teacher, so no manager bot is involved.
            'managerBot' => null,
        ]);
    }

    public function update(Teacher $teacher): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'bot_shared',
            'data' => null,
            'code' => 'bot_shared',
        ], 409);
    }
}
