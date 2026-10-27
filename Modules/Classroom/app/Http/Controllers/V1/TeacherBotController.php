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
    public function show(TeacherContext $context): JsonResponse
    {
        $bot = TeacherBot::where('teacher_id', $context->id())->first();

        return $this->successResponse('OK', [
            'bot' => $bot ? ['username' => $bot->username, 'link' => $bot->link(), 'status' => 'active'] : null,
            // The link to give parents: the teacher's own bot. The shared platform bot only counts while it
            // still serves parents; otherwise it is null until the teacher creates their bot.
            'parentsLink' => $bot?->link() ?? (config('services.telegram.shared_parents_bot') ? $this->sharedBotLink() : null),
            'managerConfigured' => $this->managerUsername() !== '',
            // The link the teacher generated stays available until the bot is created.
            'pending' => $this->pendingPayload($this->pendingRequest($context->id())),
        ]);
    }

    // Returns the manager bot's start link. The teacher opens it in Telegram and finishes there.
    // The link never expires: asking again returns the same one, until the bot is created.
    public function connect(TeacherContext $context): JsonResponse
    {
        if ($this->managerUsername() === '' || ! config('services.telegram.manager_token')) {
            return $this->fail('manager_bot_not_configured', 503);
        }

        $request = $this->pendingRequest($context->id()) ?? TeacherBotRequest::create([
            'teacher_id' => $context->id(),
            'code' => Str::random(32),
            'expires_at' => null,
        ]);

        return $this->successResponse('OK', $this->pendingPayload($request));
    }

    private function pendingRequest(int $teacherId): ?TeacherBotRequest
    {
        return TeacherBotRequest::where('teacher_id', $teacherId)->latest('id')->first();
    }

    private function pendingPayload(?TeacherBotRequest $request): ?array
    {
        return $request ? [
            'startLink' => 'https://t.me/'.$this->managerUsername().'?start='.$request->code,
        ] : null;
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

    private function sharedBotLink(): ?string
    {
        $username = ltrim((string) config('services.telegram.bot_username'), '@');

        return $username !== '' ? 'https://t.me/'.$username : null;
    }

    private function managerUsername(): string
    {
        return ltrim((string) config('services.telegram.manager_username'), '@');
    }
}
