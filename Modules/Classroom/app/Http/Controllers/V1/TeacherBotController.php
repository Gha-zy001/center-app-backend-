<?php

namespace Modules\Classroom\app\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Modules\Classroom\app\Models\TeacherBot;
use Modules\Classroom\app\Models\TeacherBotRequest;
use Modules\Classroom\app\Services\TelegramClient;
use Modules\Teachers\app\Support\TeacherContext;

/** The signed-in teacher's own parents bot: see it, start creating it, or remove it. */
class TeacherBotController extends ClassroomController
{
    private const REQUEST_MINUTES = 30;

    public function show(TeacherContext $context): JsonResponse
    {
        $bot = TeacherBot::where('teacher_id', $context->id())->first();

        return $this->successResponse('OK', [
            'bot' => $bot ? ['username' => $bot->username, 'link' => $bot->link(), 'status' => 'active'] : null,
            'managerConfigured' => $this->managerUsername() !== '',
        ]);
    }

    // Returns the manager bot's start link. The teacher opens it in Telegram and finishes there.
    public function connect(TeacherContext $context): JsonResponse
    {
        if ($this->managerUsername() === '' || ! config('services.telegram.manager_token')) {
            return $this->fail('manager_bot_not_configured', 503);
        }

        TeacherBotRequest::where('teacher_id', $context->id())->delete();

        $request = TeacherBotRequest::create([
            'teacher_id' => $context->id(),
            'code' => Str::random(32),
            'expires_at' => now()->addMinutes(self::REQUEST_MINUTES),
        ]);

        return $this->successResponse('OK', [
            'startLink' => 'https://t.me/'.$this->managerUsername().'?start='.$request->code,
            'expiresAt' => $request->expires_at->toIso8601String(),
        ]);
    }

    // Removing the bot also removes the parents linked through it (they can link again through any bot).
    public function destroy(TeacherContext $context, TelegramClient $telegram): JsonResponse
    {
        $bot = TeacherBot::where('teacher_id', $context->id())->first();

        if ($bot) {
            $telegram->withToken($bot->token)->deleteWebhook();
            $bot->delete();
        }

        return $this->successResponse('OK');
    }

    private function managerUsername(): string
    {
        return ltrim((string) config('services.telegram.manager_username'), '@');
    }
}
