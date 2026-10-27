<?php

namespace Modules\Packages\app\Console;

use Illuminate\Console\Command;
use Modules\Packages\app\Services\SubscriptionService;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Deactivate the teachers whose package has ended.';

    public function handle(SubscriptionService $subscriptions): int
    {
        $this->info("Suspended {$subscriptions->expireDue()} teacher account(s).");

        return self::SUCCESS;
    }
}
