<?php

namespace Modules\Classroom\app\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\app\Services\ParentLinker;

/**
 * Where Telegram delivers what parents send to the bot (production). Anyone can reach the URL,
 * so only requests carrying the secret registered with setWebhook are accepted.
 */
class TelegramWebhookController extends ClassroomController
{
    public function __invoke(Request $request, ParentLinker $linker): JsonResponse
    {
        $secret = (string) config('services.telegram.webhook_secret');

        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return $this->fail('forbidden', 403);
        }

        $linker->handle($request->all());

        return $this->successResponse('OK');
    }
}
