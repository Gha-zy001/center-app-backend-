<?php

namespace Modules\Classroom\app\Console;

use Illuminate\Console\Command;
use Modules\Classroom\app\Services\ParentLinker;
use Modules\Classroom\app\Services\TelegramClient;

/**
 * Local development: asks Telegram for new messages instead of waiting for a webhook (which needs
 * a public https URL). Production uses `telegram:webhook` instead.
 */
class PollTelegramCommand extends Command
{
    protected $signature = 'telegram:poll';

    protected $description = 'Receive the parents\' bot messages by long polling (local development).';

    public function handle(TelegramClient $telegram, ParentLinker $linker): int
    {
        if (! $telegram->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        // A bot cannot poll and have a webhook at the same time.
        $telegram->deleteWebhook();
        $this->info('Polling Telegram. Press Ctrl+C to stop.');

        $offset = 0;

        while (true) {
            foreach ($telegram->getUpdates($offset) as $update) {
                $offset = $update['update_id'] + 1;
                $linker->handle($update);
            }
        }
    }
}
