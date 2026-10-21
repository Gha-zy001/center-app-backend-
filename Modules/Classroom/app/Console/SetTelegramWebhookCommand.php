<?php

namespace Modules\Classroom\app\Console;

use Illuminate\Console\Command;
use Modules\Classroom\app\Services\TelegramClient;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook {url : The public https URL of /api/v1/telegram/webhook}';

    protected $description = 'Point the parents\' bot at this server (production).';

    public function handle(TelegramClient $telegram): int
    {
        if (! $telegram->setWebhook($this->argument('url'))) {
            $this->error('Telegram refused the webhook (check the token, the URL and TELEGRAM_WEBHOOK_SECRET).');

            return self::FAILURE;
        }

        $this->info('Webhook set.');

        return self::SUCCESS;
    }
}
