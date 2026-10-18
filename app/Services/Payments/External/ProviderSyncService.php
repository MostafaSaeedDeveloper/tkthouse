<?php

namespace App\Services\Payments\External;

use App\Models\ExternalTransaction;
use App\Models\IntegrationAccount;
use App\Models\PaymentAttempt;
use App\Models\ProviderSync;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Payments\ProviderCapabilities;
use App\Support\PaymentEnvironment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;

/**
 * "Sync now" / background sync for ONE merchant account, limited to what the provider's official API can do: re-ask about
 * records we already know (single lookups). There is no "fetch everything" — no official listing API exists (see the audit).
 *
 *  A) still-open local payment attempts of the account → the existing settlement path (idempotent; amount/currency/ownership rules);
 *  B) external transactions of the account (looked up or imported) → re-fetched and updated; nothing here touches orders.
 *
 * Bounded (default 100 records per run), oldest-checked first, so repeated runs walk through everything; an HTTP 429 stops the run
 * cleanly (status "partial", rate_limited) and the next run continues where it stopped. Every run is logged in provider_syncs.
 */
class ProviderSyncService
{
    public function __construct(private readonly ExternalTransactionService $external, private readonly PaymentService $payments)
    {
    }

    public function run(IntegrationAccount $account, string $trigger = 'manual', ?User $by = null, int $limit = 100): ProviderSync
    {
        $sync = ProviderSync::query()->create(['integration_account_id' => $account->id, 'trigger' => $trigger, 'status' => 'running', 'examined' => 0, 'updated' => 0, 'errors' => 0, 'rate_limited' => false, 'started_by' => $by?->id, 'started_at' => now()]);

        if ($account->archived_at !== null || ! ProviderCapabilities::canLookup($account->provider)) {
            return $this->finish($sync, 'skipped', 'This account/provider has no official lookup API to sync with.');
        }
        if ($account->provider !== 'fake' && ! PaymentEnvironment::liveGatewaysAllowed()) {
            return $this->finish($sync, 'skipped', 'Live gateways are disabled in this environment.');
        }

        $examined = $updated = $errors = 0;
        $limited = false;
        $messages = [];
        $budget = max(1, min(500, $limit));

        // A) open attempts of this account
        $attempts = PaymentAttempt::query()->where('integration_account_id', $account->id)->where('status', PaymentAttempt::INITIATED)
            ->where('created_at', '<=', now()->subMinutes(2))->where('created_at', '>=', now()->subHours(72))
            ->where(fn ($q) => $q->whereNotNull('transaction_id')->orWhereNotNull('provider_order_id'))->whereHas('order')
            ->orderByRaw('last_checked_at IS NOT NULL')->orderBy('last_checked_at')->limit($budget)->get();
        foreach ($attempts as $attempt) {
            $examined++;
            try {
                $outcome = $this->payments->refreshAttempt($attempt);
                if ($outcome !== PaymentService::OUTCOME_PENDING) {
                    $updated++;
                }
            } catch (\Throwable $e) {
                if ($this->isRateLimit($e)) {
                    $limited = true;
                    break;
                }
                $errors++;
                $this->note($messages, "attempt {$attempt->internal_reference}: ".class_basename($e));
            }
            $this->pace($account);
        }
        $budget -= $attempts->count();

        // B) external transactions of this account
        if (! $limited && $budget > 0) {
            $rows = ExternalTransaction::query()->where('integration_account_id', $account->id)
                ->orderByRaw('last_checked_at IS NOT NULL')->orderBy('last_checked_at')->limit($budget)->get();
            foreach ($rows as $row) {
                $examined++;
                try {
                    $before = [$row->status, $row->is_refunded, $row->is_voided, $row->amount_minor, $row->refunded_amount_minor];
                    $fresh = $this->external->lookup($account, $row->provider_transaction_id, $by);
                    if ($before !== [$fresh->status, $fresh->is_refunded, $fresh->is_voided, $fresh->amount_minor, $fresh->refunded_amount_minor]) {
                        $updated++;
                    }
                } catch (ValidationException $e) {
                    $msg = (string) collect($e->errors())->flatten()->first();
                    if (str_contains($msg, 'rate limiting')) {
                        $limited = true;
                        break;
                    }
                    $errors++;
                    $this->note($messages, "transaction {$row->provider_transaction_id}: ".mb_substr($msg, 0, 120));
                    $row->forceFill(['last_checked_at' => now()])->save(); // do not retry the same failing row first on the next run
                } catch (\Throwable $e) {
                    $errors++;
                    $this->note($messages, "transaction {$row->provider_transaction_id}: ".class_basename($e));
                }
                $this->pace($account);
            }
        }

        $sync->forceFill(['examined' => $examined, 'updated' => $updated, 'errors' => $errors, 'rate_limited' => $limited]);
        $status = match (true) {
            $examined > 0 && $errors === $examined => 'failed',
            $limited || $errors > 0 => 'partial',
            default => 'succeeded',
        };

        return $this->finish($sync, $status, $limited ? 'The provider is rate limiting: stopped early, the next run continues. '.implode(' | ', $messages) : implode(' | ', $messages));
    }

    /** The newest sync of each account, for the UI. @return \Illuminate\Support\Collection<int, ProviderSync> keyed by account id */
    public static function latestByAccount()
    {
        return ProviderSync::query()->whereIn('id', ProviderSync::query()->selectRaw('max(id)')->groupBy('integration_account_id'))->get()->keyBy('integration_account_id');
    }

    private function finish(ProviderSync $sync, string $status, ?string $message): ProviderSync
    {
        $sync->forceFill(['status' => $status, 'message' => $message !== '' ? mb_substr((string) $message, 0, 1000) : null, 'finished_at' => now()])->save();

        return $sync;
    }

    private function isRateLimit(\Throwable $e): bool
    {
        return $e instanceof RequestException && $e->response?->status() === 429;
    }

    private function note(array &$messages, string $m): void
    {
        if (count($messages) < 5) {
            $messages[] = $m;
        }
    }

    private function pace(IntegrationAccount $account): void
    {
        if ($account->provider !== 'fake' && ! app()->runningUnitTests()) {
            usleep(150_000); // ~6 requests/second at most
        }
    }
}
