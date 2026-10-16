<?php

namespace App\Console\Commands;

use App\Models\IntegrationAccount;
use App\Models\PaymentMethod;
use Illuminate\Console\Command;

/**
 * Copies gateway credentials that are stored in plaintext (payment_methods.config) or in .env into ENCRYPTED, DISABLED,
 * UNVERIFIED integration accounts. It never modifies or deletes the legacy values, so runtime behaviour is unchanged
 * until an operator verifies and enables the new accounts. Rollback removes only the accounts this command created.
 */
class ImportLegacyCredentials extends Command
{
    protected $signature = 'settings:import-legacy-credentials {--dry-run : show what would be created, write nothing} {--rollback : delete the accounts created by a previous import}';

    protected $description = 'Import legacy gateway credentials into encrypted integration accounts (non-destructive).';

    public function handle(): int
    {
        if ($this->option('rollback')) {
            $q = IntegrationAccount::query()->where('source', 'legacy_import')->where('enabled', false);
            $n = $q->count();
            $this->option('dry-run') ? $this->info("Would delete {$n} imported (disabled) accounts.") : $this->info('Deleted '.$q->delete()." imported accounts. Legacy credentials were never touched.");

            return self::SUCCESS;
        }

        $plans = [];
        foreach (PaymentMethod::query()->whereIn('provider', ['paymob', 'fawaterak'])->get() as $m) {
            $c = is_array($m->config) ? $m->config : [];
            if (blank($c['api_key'] ?? null)) {
                continue;
            }
            $key = $m->provider.'|'.substr(hash('sha256', (string) $c['api_key']), 0, 10);
            $plans[$key] ??= ['provider' => $m->provider, 'secrets' => ['api_key' => (string) $c['api_key']], 'config' => [], 'methods' => []];
            $plans[$key]['methods'][] = $m->code;
            foreach (['iframe_id' => 'iframe_id', 'integration_id' => 'integration_'.str_replace('paymob_', '', $m->code), 'provider_key' => 'provider_'.str_replace('fawaterak_', '', $m->code)] as $from => $to) {
                if (filled($c[$from] ?? null)) {
                    $plans[$key]['config'][$to] = (string) $c[$from];
                }
            }
        }

        if (filled(config('services.paymob.api_key')) && ! collect($plans)->contains(fn ($p) => ($p['secrets']['api_key'] ?? null) === config('services.paymob.api_key'))) {
            $plans['env|paymob'] = ['provider' => 'paymob', 'secrets' => array_filter(['api_key' => config('services.paymob.api_key'), 'secret_key' => config('services.paymob.secret_key'), 'hmac_secret' => config('payments.paymob.hmac_secret')]), 'config' => array_filter(['iframe_id' => config('services.paymob.iframe_id'), 'public_key' => config('services.paymob.public_key')]), 'methods' => ['.env']];
        }

        if (! $plans) {
            $this->info('Nothing to import.');

            return self::SUCCESS;
        }

        foreach ($plans as $key => $plan) {
            $name = ucfirst($plan['provider']).' — imported '.substr(hash('sha256', $key), 0, 6);
            $this->line(sprintf('%s %s  secrets: %s  from: %s', $this->option('dry-run') ? '[dry-run]' : '[create]', $name, implode(',', array_keys($plan['secrets'])).' (masked)', implode(',', $plan['methods'])));
            if ($this->option('dry-run') || IntegrationAccount::query()->where('provider', $plan['provider'])->where('legacy_ref', $key)->exists()) {
                continue;
            }
            IntegrationAccount::create([
                'provider' => $plan['provider'], 'name' => $name, 'environment' => 'production', 'enabled' => false, 'status' => 'unverified',
                'config' => $plan['config'], 'secrets' => $plan['secrets'], 'source' => 'legacy_import', 'legacy_ref' => $key,
                'credential_fingerprint' => IntegrationAccount::fingerprint($plan['config'], $plan['secrets']),
            ]);
        }

        $this->info($this->option('dry-run') ? 'Dry run only — nothing was written.' : 'Imported as DISABLED + UNVERIFIED. Legacy values are untouched; verify each account in Settings → Integrations.');

        return self::SUCCESS;
    }
}
