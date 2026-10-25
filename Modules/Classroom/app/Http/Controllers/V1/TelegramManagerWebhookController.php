<?php

namespace Modules\Classroom\app\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\app\Services\ManagerBotHandler;

/** Where Telegram delivers what reaches the manager bot. Only the secret registered with setWebhook gets in. */
class TelegramManagerWebhookController extends ClassroomController
{
    public function __invoke(Request $request, ManagerBotHandler $handler): JsonResponse
    {
        $secret = (string) config('services.telegram.manager_webhook_secret');

        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return $this->fail('forbidden', 403);
        }

        $handler->handle($request->all());

        return $this->successResponse('OK');
    }
}
