<?php

namespace App\Services\Payments\External;

use App\Models\ExternalTransaction;
use App\Models\IntegrationAccount;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\FawaterakService;
use App\Services\Payments\ProviderCapabilities;
use App\Services\PaymobService;
use App\Support\PaymentEnvironment;
use Illuminate\Validation\ValidationException;

/**
 * Brings provider-side transactions into TKT House WITHOUT touching orders:
 *   - lookup(): one official single-record call, made with the merchant account the admin chose (never a default account);
 *   - upsert(): idempotent on (provider, account, provider transaction id).
 * An external transaction is linked to a local payment attempt only when a provider id matches EXACTLY under the same account
 * and exactly one attempt matches. It never creates an order and never changes an order or an attempt (settlement stays in
 * PaymentService, driven by the attempt's own inquiry / signed webhook).
 */
class ExternalTransactionService
{
    public function __construct(private readonly PaymobService $paymob, private readonly FawaterakService $fawaterak)
    {
    }

    /** @throws ValidationException when the account can't be used or the provider doesn't know the id for THIS account */
    public function lookup(IntegrationAccount $account, string $id, ?User $by = null): ExternalTransaction
    {
        $id = trim($id);
        $this->assertAccount($account);
        if ($id === '' || mb_strlen($id) > 120) {
            $this->fail('Enter a valid provider transaction id.');
        }
        if (! ProviderCapabilities::canLookup($account->provider)) {
            $this->fail('This provider has no official single-transaction lookup.');
        }

        try {
            $data = match ($account->provider) {
                'paymob' => $this->normalizePaymob($this->paymob->inquireTransaction(null, $id, $account)),
                'fawaterak' => $this->normalizeFawaterak($this->fawaterak->inquireInvoice(null, $id, $account), $id),
                'fake' => $this->fakeLookup($id),
            };
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $code = $e->response?->status();
            $this->fail($code === 404 || $code === 401 || $code === 403
                ? "The provider did not return this transaction for the merchant account \"{$account->name}\". Check the id and that it belongs to this account."
                : ($code === 429 ? 'The provider is rate limiting requests. Try again in a minute.' : "The provider answered with an error (HTTP {$code}). Nothing was saved."));
        } catch (\Throwable $e) {
            $this->fail('The provider could not be reached or is disabled in this environment; nothing was saved ('.class_basename($e).').');
        }

        if (blank($data['provider_transaction_id'] ?? null)) {
            $this->fail('The provider response did not contain a transaction id; nothing was saved.');
        }

        return $this->upsert($account, $data, 'api_lookup', $by);
    }

    /**
     * Idempotent write. A CSV row never overwrites a row that was verified by the provider's API.
     *
     * @param  array<string, mixed>  $data  normalized fields (see normalize*)
     */
    public function upsert(IntegrationAccount $account, array $data, string $source, ?User $by = null): ExternalTransaction
    {
        $key = ['provider' => $account->provider, 'integration_account_id' => $account->id, 'provider_transaction_id' => (string) $data['provider_transaction_id']];
        $existing = ExternalTransaction::query()->where($key)->first();
        if ($existing && $existing->verified_at !== null && $source === 'csv_import') {
            return $existing; // an official lookup is more trustworthy than a spreadsheet row
        }

        $attributes = array_diff_key($data, ['provider_transaction_id' => 1]) + ['last_checked_at' => $source === 'api_lookup' ? now() : null];
        $attributes['source'] = $existing && $existing->source === 'api_lookup' ? 'api_lookup' : $source;
        $attributes['verified_at'] = $source === 'api_lookup' ? now() : $existing?->verified_at;
        $attributes['imported_by'] = $existing?->imported_by ?? $by?->id;
        $values = array_filter($attributes, fn ($v, $k) => $v !== null || in_array($k, ['verified_at', 'last_checked_at'], true), ARRAY_FILTER_USE_BOTH);
        try {
            $row = ExternalTransaction::query()->updateOrCreate($key, $values);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // two imports of the same provider transaction raced: the unique key kept it to one row, this one just updates it
            $row = ExternalTransaction::query()->updateOrCreate($key, $values);
        }

        $this->link($row);

        return $row->fresh();
    }

    /** Exact, same-account, unambiguous match only. Anything else stays unlinked. */
    public function link(ExternalTransaction $row): void
    {
        $matches = PaymentAttempt::query()->where('provider', $row->provider)->where('integration_account_id', $row->integration_account_id)
            ->where(function ($q) use ($row) {
                $q->where('transaction_id', $row->provider_transaction_id);
                if (filled($row->provider_order_id)) {
                    $q->orWhere('provider_order_id', $row->provider_order_id);
                }
                if (filled($row->provider_reference)) {
                    $q->orWhere('reference', $row->provider_reference);
                }
            })->get(['id', 'order_id', 'amount']);

        $one = $matches->count() === 1 ? $matches->first() : null;
        $snapshot = (array) $row->snapshot;
        unset($snapshot['amount_mismatch'], $snapshot['link_ambiguous']);
        if ($matches->count() > 1) {
            $snapshot['link_ambiguous'] = true;
        }
        if ($one && $row->amount_minor !== null && (int) round(((float) $one->amount) * 100) !== (int) $row->amount_minor) {
            $snapshot['amount_mismatch'] = (int) round(((float) $one->amount) * 100);
        }
        $row->forceFill(['matched_attempt_id' => $one?->id, 'snapshot' => $snapshot ?: null])->save();
    }

    /* ----------------------------------------------------------------------------- normalisation */

    /** @return array<string, mixed> */
    public function normalizePaymob(array $tx): array
    {
        $amount = data_get($tx, 'amount_cents');
        $refunded = data_get($tx, 'refunded_amount_cents');
        $isRefunded = filter_var(data_get($tx, 'is_refunded'), FILTER_VALIDATE_BOOLEAN);
        $isVoided = filter_var(data_get($tx, 'is_voided'), FILTER_VALIDATE_BOOLEAN);
        $success = filter_var(data_get($tx, 'success'), FILTER_VALIDATE_BOOLEAN);
        $pending = filter_var(data_get($tx, 'pending'), FILTER_VALIDATE_BOOLEAN);
        $status = match (true) {
            $isVoided => 'voided',
            $isRefunded => 'refunded',
            is_numeric($refunded) && (int) $refunded > 0 => 'partially_refunded',
            $success && ! $pending => 'paid',
            $pending => 'pending',
            data_get($tx, 'id') !== null => 'failed',
            default => 'unknown',
        };
        $created = data_get($tx, 'created_at');

        return [
            'provider_transaction_id' => data_get($tx, 'id') !== null ? (string) data_get($tx, 'id') : null,
            'provider_order_id' => data_get($tx, 'order.id') !== null ? (string) data_get($tx, 'order.id') : null,
            'provider_reference' => $this->clean(data_get($tx, 'order.merchant_order_id')),
            'amount_minor' => is_numeric($amount) ? (int) $amount : null,
            'currency' => $this->clean(data_get($tx, 'currency'), 8),
            'status' => $status,
            'provider_status' => $this->clean($success ? 'success' : ($pending ? 'pending' : 'failed'), 60),
            'payment_method' => $this->clean(data_get($tx, 'source_data.sub_type') ?: data_get($tx, 'source_data.type'), 60),
            'refunded_amount_minor' => is_numeric($refunded) ? (int) $refunded : null,
            'is_refunded' => $isRefunded, 'is_voided' => $isVoided,
            'provider_created_at' => $this->time($created),
            'paid_at' => ($success && ! $pending) ? $this->time($created) : null,
            'customer_label' => null, // Paymob's transaction lookup is not used to collect customer data
            'snapshot' => array_filter(['is_auth' => data_get($tx, 'is_auth'), 'is_capture' => data_get($tx, 'is_capture'), 'parent_transaction' => data_get($tx, 'parent_transaction')], fn ($v) => $v !== null && $v !== false) ?: null,
        ];
    }

    /** @return array<string, mixed> */
    public function normalizeFawaterak(array $payload, string $requestedId): array
    {
        $d = (array) ($payload['data'] ?? []);
        if ($d === [] || data_get($d, 'invoice_id') === null) {
            $this->fail('The provider did not return an invoice for this id.');
        }
        $txs = array_values((array) ($d['invoice_transactions'] ?? []));
        $paid = filter_var($d['paid'] ?? null, FILTER_VALIDATE_BOOLEAN) || filled($d['paid_at'] ?? null);
        $failed = ! $paid && collect($txs)->contains(fn ($t) => in_array(strtolower((string) ($t['status'] ?? '')), ['failed', 'fail', 'declined', 'error'], true));
        $total = $d['total'] ?? null;
        $commission = $d['commission'] ?? null;
        $lastRef = collect($txs)->pluck('refrence_id')->filter(fn ($v) => filled($v) && $v !== '-')->last();

        return [
            'provider_transaction_id' => (string) $d['invoice_id'],
            'provider_order_id' => null,
            'provider_reference' => $this->clean($lastRef ?: ($d['invoice_key'] ?? null), 190),
            'amount_minor' => is_numeric($total) ? (int) round(((float) $total) * 100) : null,
            'currency' => $this->clean($d['currency'] ?? null, 8),
            'status' => $paid ? 'paid' : ($failed ? 'failed' : 'pending'),
            'provider_status' => $this->clean($paid ? 'paid' : ($failed ? 'failed' : 'unpaid'), 60),
            'payment_method' => $this->clean($d['payment_method'] ?? null, 60),
            'fee_minor' => is_numeric($commission) ? (int) round(((float) $commission) * 100) : null,
            'is_refunded' => false, 'is_voided' => false, // no refund/void information in this response
            'provider_created_at' => $this->time($d['invoice_created_at'] ?? null),
            'paid_at' => $paid ? $this->time($d['paid_at'] ?? null) : null,
            'customer_label' => $this->clean($d['customer_email'] ?? null, 190),
            'snapshot' => ['invoice_key' => $this->clean($d['invoice_key'] ?? null, 190), 'transactions' => count($txs)],
        ];
    }

    /** Dev/test provider: deterministic, offline, only where the fake gateway is allowed. */
    private function fakeLookup(string $id): array
    {
        if (! PaymentEnvironment::fakeGatewayAllowed()) {
            $this->fail('The fake gateway is disabled in this environment.');
        }
        $n = abs(crc32($id));

        return [
            'provider_transaction_id' => $id, 'provider_order_id' => null, 'provider_reference' => 'FAKE-'.substr($id, -6), 'amount_minor' => (1000 + $n % 90000),
            'currency' => 'EGP', 'status' => 'paid', 'provider_status' => 'success', 'payment_method' => 'card', 'is_refunded' => false, 'is_voided' => false,
            'provider_created_at' => now()->subDays($n % 20), 'paid_at' => now()->subDays($n % 20), 'customer_label' => null, 'snapshot' => ['fake' => true],
        ];
    }

    private function assertAccount(IntegrationAccount $account): void
    {
        if (! in_array($account->provider, ['paymob', 'fawaterak', 'fake'], true) || $account->archived_at !== null) {
            $this->fail('Choose an active payment provider account.');
        }
    }

    private function clean(mixed $v, int $max = 190): ?string
    {
        $v = is_scalar($v) ? trim((string) $v) : '';

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private function time(mixed $v): ?\Illuminate\Support\Carbon
    {
        if (blank($v)) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($v);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['provider' => $message]);
    }
}
