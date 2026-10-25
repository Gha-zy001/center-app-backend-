<?php

namespace Modules\Classroom\app\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\app\Models\TeacherBot;
use Modules\Classroom\app\Services\ParentLinker;
use Modules\Classroom\app\Services\TelegramClient;

/**
 * Where Telegram delivers what parents send to one teacher's own bot. The URL holds a random key and
 * each bot has its own secret; an unknown key and a wrong secret look the same from outside.
 */
class TelegramBotWebhookController extends ClassroomController
{
    public function __invoke(Request $request, string $key, ParentLinker $linker, TelegramClient $telegram): JsonResponse
    {
        $bot = TeacherBot::where('webhook_key', $key)->first();
        $given = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! $bot || ! hash_equals($bot->webhook_secret, $given)) {
            return $this->fail('forbidden', 403);
        }

        $linker->forBot($bot, $telegram)->handle($request->all());

        return $this->successResponse('OK');
    }
}
