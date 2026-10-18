<?php

namespace App\Console\Commands;

use App\Models\IntegrationAccount;
use App\Services\Payments\External\ProviderSyncService;
use Illuminate\Console\Command;

class SyncProviderTransactions extends Command
{
    protected $signature = 'payments:sync {--account= : only this merchant account id} {--limit=100 : max records per account per run}';

    protected $description = 'Refresh known transactions with each provider account (single official lookups only; there is no list-all API). Logged in provider_syncs.';

    public function handle(ProviderSyncService $sync): int
    {
        $accounts = IntegrationAccount::query()->whereIn('provider', ['paymob', 'fawaterak', 'fake'])->whereNull('archived_at')
            ->when($this->option('account'), fn ($q) => $q->whereKey((int) $this->option('account')))->orderBy('id')->get();

        foreach ($accounts as $account) {
            $run = $sync->run($account, 'scheduled', null, (int) $this->option('limit'));
            $this->line("account #{$account->id} {$account->name}: {$run->status} (examined {$run->examined}, updated {$run->updated}, errors {$run->errors})");
        }

        return self::SUCCESS;
    }
}
