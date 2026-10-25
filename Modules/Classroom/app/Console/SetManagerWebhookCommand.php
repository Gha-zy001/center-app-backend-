<?php

namespace Modules\Classroom\app\Console;

use Illuminate\Console\Command;
use Modules\Classroom\app\Services\TelegramClient;

class SetManagerWebhookCommand extends Command
{
    protected $signature = 'telegram:manager-webhook {url : The public https URL of /api/v1/telegram/manager/webhook}';

    protected $description = 'Point the manager bot (the one that creates teachers\' own bots) at this server.';

    public function handle(TelegramClient $telegram): int
    {
        $manager = $telegram->withToken(config('services.telegram.manager_token'));

        if (! $manager->setWebhook($this->argument('url'), config('services.telegram.manager_webhook_secret'), ['message', 'managed_bot'])) {
            $this->error('Telegram refused the webhook (check TELEGRAM_MANAGER_BOT_TOKEN, TELEGRAM_MANAGER_WEBHOOK_SECRET and the URL).');

            return self::FAILURE;
        }

        $this->info('Manager webhook set.');

        return self::SUCCESS;
    }
}
