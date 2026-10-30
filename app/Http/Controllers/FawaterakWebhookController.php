<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Webhooks\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Fawaterak webhooks (official reference: fawaterak-api.readme.io/reference/web-hook). The URL given to Fawaterak must end in `_json`.
 * What the documentation does and does not give us:
 *  - paid:      hashKey = HMAC-SHA256("InvoiceId=..&InvoiceKey=..&PaymentMethod=..", vendor key). The hash does NOT cover status or amount,
 *               so a paid webhook only triggers the server-side invoice inquiry; it never marks anything paid itself.
 *  - failed:    the documented sample has NO hashKey => cannot be authenticated => stored as an informational, unauthenticated event.
 *  - cancel:    hashKey = HMAC-SHA256("referenceId=..&PaymentMethod=..") (Fawry/Aman/Masary only); how referenceId maps to our invoice id
 *               is not documented => matched only by an exact stored id, otherwise left unmatched for review.
 *  - refund:    no hash documented, status "approved" is an approval, not proof of money returned => stored, flags a manual refund.
 */
class FawaterakWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookService $webhooks, PaymentService $payments)
    {
        $p = (array) $request->json()->all() ?: $request->all();

        $type = match (true) {
            isset($p['invoice_status']) && strtolower((string) $p['invoice_status']) === 'paid' => 'payment.succeeded',
            isset($p['errorMessage']) || isset($p['response']['gatewayCode']) => 'payment.failed',
            isset($p['referenceId'], $p['status']) && strtoupper((string) $p['status']) === 'EXPIRED' => 'payment.cancelled',
            isset($p['transactionId'], $p['status'], $p['amount']) && ! isset($p['invoice_id']) => 'refund', // any status: the handler decides; only "approved" is documented
            default => 'unknown',
        };

        // Find OUR attempt by the exact ids we stored; nothing is matched by amount or e-mail.
        $ids = array_values(array_filter([(string) ($p['invoice_id'] ?? ''), (string) ($p['transactionId'] ?? ''), (string) ($p['transactionKey'] ?? '')]));
        $refs = array_values(array_filter([(string) ($p['referenceNumber'] ?? ''), (string) ($p['referenceId'] ?? '')]));
        $attempt = PaymentAttempt::query()->where('provider', 'fawaterak')
            ->where(fn ($q) => $q->when($ids, fn ($w) => $w->whereIn('transaction_id', $ids))->when($refs, fn ($w) => $w->orWhereIn('provider_reference', $refs)))
            ->orderByDesc('id')->first();
        $account = $attempt?->integration_account_id ? $attempt->integrationAccount : null;

        $vendorKey = (string) ($account?->secret('vendor_key') ?? '');
        $received = (string) ($p['hashKey'] ?? '');
        $expected = match ($type) {
            'payment.succeeded' => 'InvoiceId='.($p['invoice_id'] ?? '').'&InvoiceKey='.($p['invoice_key'] ?? '').'&PaymentMethod='.($p['payment_method'] ?? ''),
            'payment.cancelled' => 'referenceId='.($p['referenceId'] ?? '').'&PaymentMethod='.($p['paymentMethod'] ?? ''),
            default => null,
        };
        $signature = match (true) {
            $expected === null => 'unsupported',      // the provider documents no hash for this event type
            $vendorKey === '' => 'unverifiable',      // no vendor key stored for this account
            $received === '' => 'missing',
            default => hash_equals(hash_hmac('sha256', $expected, $vendorKey), strtolower($received)) ? 'valid' : 'invalid',
        };

        $tx = (string) ($p['invoice_id'] ?? $p['transactionId'] ?? $p['referenceId'] ?? '');
        [$event] = $webhooks->record('fawaterak', $type, [$tx, $type, $p['amount'] ?? '', $p['invoice_status'] ?? $p['status'] ?? '', $p['currency'] ?? '', $p['approvedAt'] ?? ''], $tx ?: null, $signature, $p, $account, $attempt);

        if ($attempt && ! empty($p['referenceNumber']) && blank($attempt->provider_reference)) {
            PaymentAttempt::query()->whereKey($attempt->id)->update(['provider_reference' => mb_substr((string) $p['referenceNumber'], 0, 250)]);
        }

        if ($signature === 'invalid' || $signature === 'missing' && $expected !== null && $vendorKey !== '') {
            Log::warning('Fawaterak webhook rejected: bad signature.', ['type' => $type]);
            $webhooks->finish($event, 'failed', 'Invalid or missing signature');

            return response()->json(['received' => false], 403);
        }

        if (! $attempt && $type !== 'refund') {
            $webhooks->finish($event, 'needs_review', 'No payment attempt matches the ids in this event.');

            return response()->json(['received' => true, 'matched' => false]);
        }

        // Every type goes through the same gate: payment events only trigger an inquiry; refund events go to the refund module.
        $outcome = $webhooks->retry($event->fresh());

        return response()->json(['received' => true, 'authenticated' => $signature === 'valid', 'outcome' => $outcome]);
    }
}
