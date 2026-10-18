<?php

namespace App\Services\Payments\External;

use App\Models\IntegrationAccount;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Imports a transaction export downloaded by a person from the provider's dashboard (no official listing API exists).
 * Rows are stored as external transactions marked "from CSV, not verified"; they never create orders or change any attempt/order.
 * Idempotent: re-importing the same file updates the same rows. The column mapping is chosen by the admin (provider exports differ).
 */
class ExternalTransactionCsvImporter
{
    public const MAX_ROWS = 5000;
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** field => [label, required, header synonyms (lower case, normalised)] */
    public const FIELDS = [
        'provider_transaction_id' => ['Transaction ID', true, ['transaction id', 'transactionid', 'txn id', 'transaction', 'id', 'invoice id', 'invoiceid', 'invoice_id']],
        'amount' => ['Amount', true, ['amount', 'total', 'amount egp', 'value', 'amount cents', 'amount_cents']],
        'currency' => ['Currency', false, ['currency', 'ccy']],
        'status' => ['Status', false, ['status', 'transaction status', 'state', 'result']],
        'payment_method' => ['Payment method', false, ['payment method', 'payment_method', 'method', 'source', 'channel', 'card type']],
        'provider_created_at' => ['Created at', false, ['created at', 'created_at', 'date', 'transaction date', 'created', 'invoice created at']],
        'paid_at' => ['Paid at', false, ['paid at', 'paid_at', 'payment date', 'paid date']],
        'provider_order_id' => ['Provider order ID', false, ['order id', 'order_id', 'paymob order id', 'provider order id']],
        'provider_reference' => ['Reference / merchant order ID', false, ['merchant order id', 'merchant_order_id', 'reference', 'reference number', 'referencenumber', 'invoice key', 'invoice_key', 'merchant reference']],
        'customer_label' => ['Customer e-mail', false, ['email', 'customer email', 'customer_email', 'e-mail']],
    ];

    public function __construct(private readonly ExternalTransactionService $service)
    {
    }

    /** @return array{headers: list<string>, preview: list<list<string>>, rows: int, delimiter: string} */
    public function inspect(string $path): array
    {
        $this->assertFile($path);
        [$handle, $delimiter] = $this->open($path);
        $headers = $this->row($handle, $delimiter);
        if ($headers === null || count(array_filter($headers, fn ($h) => trim((string) $h) !== '')) < 2) {
            fclose($handle);
            $this->fail('The first row must contain the column names (at least two columns).');
        }
        $preview = [];
        $rows = 0;
        while (($r = $this->row($handle, $delimiter)) !== null) {
            if ($this->blank($r)) {
                continue;
            }
            $rows++;
            if (count($preview) < 5) {
                $preview[] = $r;
            }
            if ($rows > self::MAX_ROWS) {
                break;
            }
        }
        fclose($handle);
        if ($rows === 0) {
            $this->fail('The file has no data rows.');
        }
        if ($rows > self::MAX_ROWS) {
            $this->fail('The file has more than '.self::MAX_ROWS.' rows. Split it by date and import the parts.');
        }

        return ['headers' => array_map(fn ($h) => trim((string) $h), $headers), 'preview' => $preview, 'rows' => $rows, 'delimiter' => $delimiter];
    }

    /** @param list<string> $headers @return array<string, int|null> field => column index */
    public function suggestMapping(array $headers): array
    {
        $norm = array_map(fn ($h) => $this->norm($h), $headers);
        $map = [];
        foreach (self::FIELDS as $field => [, , $synonyms]) {
            $map[$field] = null;
            foreach ($synonyms as $s) {
                $i = array_search($s, $norm, true);
                if ($i !== false) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string, int|string|null>  $mapping  field => column index
     * @return array{created:int, updated:int, skipped:int, errors:list<string>, warnings:int, rows:int}
     */
    public function import(IntegrationAccount $account, string $path, array $mapping, string $amountUnit, ?User $by = null): array
    {
        if (! in_array($account->provider, ['paymob', 'fawaterak', 'fake'], true) || $account->archived_at !== null) {
            $this->fail('Choose an active payment provider account.');
        }
        foreach (self::FIELDS as $field => [$label, $required]) {
            if ($required && ($mapping[$field] ?? '') === '') {
                $this->fail("Map a column for \"{$label}\".");
            }
        }
        $info = $this->inspect($path);
        [$handle, $delimiter] = $this->open($path);
        $this->row($handle, $delimiter); // header

        $out = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => 0, 'rows' => 0];
        $line = 1;
        while (($r = $this->row($handle, $delimiter)) !== null) {
            $line++;
            if ($this->blank($r)) {
                continue;
            }
            $out['rows']++;
            $get = fn (string $f) => isset($mapping[$f]) && $mapping[$f] !== '' ? trim((string) ($r[(int) $mapping[$f]] ?? '')) : '';

            $id = $get('provider_transaction_id');
            if ($id === '' || mb_strlen($id) > 120) {
                $this->error($out, "line {$line}: missing or invalid transaction id");

                continue;
            }
            $amount = $this->amount($get('amount'), $amountUnit);
            if ($amount === false) {
                $this->error($out, "line {$line}: amount \"{$get('amount')}\" is not a valid number");

                continue;
            }
            $created = $this->date($get('provider_created_at'));
            $paid = $this->date($get('paid_at'));
            if (($get('provider_created_at') !== '' && $created === false) || ($get('paid_at') !== '' && $paid === false)) {
                $out['warnings']++;
            }
            $status = $this->status($get('status'));
            $data = [
                'provider_transaction_id' => $id,
                'provider_order_id' => $get('provider_order_id') ?: null,
                'provider_reference' => $get('provider_reference') !== '' ? mb_substr($get('provider_reference'), 0, 190) : null,
                'amount_minor' => $amount, 'currency' => $get('currency') !== '' ? mb_substr(strtoupper($get('currency')), 0, 8) : null,
                'status' => $status, 'provider_status' => $get('status') !== '' ? mb_substr($get('status'), 0, 60) : null,
                'payment_method' => $get('payment_method') !== '' ? mb_substr($get('payment_method'), 0, 60) : null,
                'is_refunded' => $status === 'refunded', 'is_voided' => $status === 'voided',
                'provider_created_at' => $created ?: null, 'paid_at' => $paid ?: ($status === 'paid' ? ($created ?: null) : null),
                'customer_label' => $get('customer_label') !== '' ? mb_substr($get('customer_label'), 0, 190) : null,
                'snapshot' => ['csv_line' => $line],
            ];

            $existing = \App\Models\ExternalTransaction::query()->where(['provider' => $account->provider, 'integration_account_id' => $account->id, 'provider_transaction_id' => $id])->first();
            $row = $this->service->upsert($account, $data, 'csv_import', $by);
            if ($existing && $existing->verified_at !== null) {
                $out['skipped']++;      // already verified by the provider API: the spreadsheet never overrides it
            } elseif ($existing) {
                $out['updated']++;
            } else {
                $out['created']++;
            }
        }
        fclose($handle);
        unset($info);

        return $out;
    }

    /* --------------------------------------------------------------------------------- parsing */

    private function assertFile(string $path): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            $this->fail('The file is empty.');
        }
        if (filesize($path) > self::MAX_BYTES) {
            $this->fail('The file is larger than 5 MB.');
        }
    }

    /** @return array{0: resource, 1: string} */
    private function open(string $path): array
    {
        $handle = fopen($path, 'rb');
        $first = (string) fgets($handle);
        rewind($handle);
        if (str_starts_with($first, "\xEF\xBB\xBF")) {
            fseek($handle, 3);
            $first = substr($first, 3);
        }
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);

        return [$handle, (string) array_key_first($counts)];
    }

    /** @return list<string>|null */
    private function row($handle, string $delimiter): ?array
    {
        $r = fgetcsv($handle, 0, $delimiter, '"', '');

        return $r === false ? null : array_map(fn ($v) => (string) $v, $r);
    }

    private function blank(array $r): bool
    {
        return count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0;
    }

    private function norm(string $h): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', mb_strtolower($h))));
    }

    /** @return int|false|null minor units */
    private function amount(string $raw, string $unit): int|false|null
    {
        $s = preg_replace('/[^0-9.,\-]/', '', $raw);
        if ($s === '' || $s === null) {
            return false;
        }
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = strrpos($s, ',') > strrpos($s, '.') ? str_replace(',', '.', str_replace('.', '', $s)) : str_replace(',', '', $s);
        } elseif (str_contains($s, ',')) {
            $s = preg_match('/,\d{1,2}$/', $s) ? str_replace(',', '.', $s) : str_replace(',', '', $s);
        }
        if (! is_numeric($s) || (float) $s < 0 || strlen(ltrim($s, '-')) > 14) {
            return false;
        }

        return $unit === 'minor' ? (int) round((float) $s) : (int) round(((float) $s) * 100);
    }

    /** @return \Illuminate\Support\Carbon|false|null */
    private function date(string $raw)
    {
        if ($raw === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($raw);
        } catch (\Throwable) {
            return false;
        }
    }

    private function status(string $raw): string
    {
        $s = mb_strtolower($raw);

        return match (true) {
            $s === '' => 'unknown',
            (bool) preg_match('/refund/', $s) => 'refunded',
            (bool) preg_match('/void|cancel/', $s) => 'voided',
            (bool) preg_match('/fail|declin|error|reject|denied|expired/', $s) => 'failed',
            (bool) preg_match('/pend|process|initiat|unpaid|await/', $s) => 'pending',
            (bool) preg_match('/succe|paid|approv|captur|complet/', $s) => 'paid',
            default => 'unknown',
        };
    }

    private function error(array &$out, string $message): void
    {
        $out['skipped']++;
        if (count($out['errors']) < 50) {
            $out['errors'][] = $message;
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
