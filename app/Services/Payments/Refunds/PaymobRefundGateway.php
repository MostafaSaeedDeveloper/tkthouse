<?php

namespace App\Services\Payments\Refunds;

use App\Models\IntegrationAccount;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use App\Services\PaymobService;
use App\Support\PaymentEnvironment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Paymob (Egypt). Endpoints from Paymob's official "Refund & Void & Capture APIs" collection:
 *   POST https://accept.paymob.com/api/acceptance/void_refund/refund   Authorization: Token <secret key>   {transaction_id:int, amount_cents:int}
 *   POST https://accept.paymob.com/api/acceptance/void_refund/void     Authorization: Token <secret key>   {transaction_id:int}   (card only, before settlement)
 * The documentation reachable to us does not describe the response body, so an HTTP 2xx is only "accepted": the refund is confirmed
 * from documented transaction fields (success / pending / amount_cents / has_parent_transaction / is_refunded / is_voided) via an
 * inquiry with the SAME merchant account, never from the refund response itself.
 */
class PaymobRefundGateway implements RefundGateway
{
    private const BASE = 'https://accept.paymob.com/api';

    public function __construct(private readonly PaymobService $paymob)
    {
    }

    public function provider(): string
    {
        return 'paymob';
    }

    public function unsupportedReason(PaymentAttempt $attempt): ?string
    {
        $account = $attempt->integration_account_id ? $attempt->integrationAccount : null;

        return match (true) {
            ! $account => 'This payment was made before merchant accounts existed (legacy configuration); its refund API credentials are not stored.',
            $account->archived_at !== null => 'The merchant account is archived.',
            ! $account->enabled => 'The merchant account is disabled; enable it again to refund through the API.',
            ! filled($account->secret('secret_key')) => 'The merchant account has no Paymob secret key (needed for the refund API).',
            $account->refund_enabled_at === null => 'Refunds are not yet confirmed for this merchant account (sandbox verification pending).',
            ! config('payments.refunds.live_api_enabled') => 'Provider refund APIs are switched off in this environment (PAYMENTS_REFUNDS_LIVE).',
            ! PaymentEnvironment::liveGatewaysAllowed() => 'Live gateways are disabled in this environment.',
            ! ctype_digit((string) $attempt->transaction_id) => 'The Paymob transaction id is missing or not numeric.',
            default => null,
        };
    }

    public function supportsVoid(PaymentAttempt $attempt): bool
    {
        return str_ends_with((string) $attempt->method_code, '_card') && $attempt->paid_at !== null && $attempt->paid_at->gt(now()->subHours(24));
    }

    public function refund(PaymentRefund $refund, PaymentAttempt $attempt): RefundOutcome
    {
        $account = $attempt->integrationAccount;
        $isVoid = $refund->type === 'void';
        $body = ['transaction_id' => (int) $attempt->transaction_id] + ($isVoid ? [] : ['amount_cents' => $refund->amount_minor]);

        $response = Http::timeout(25)->withHeaders(['Authorization' => 'Token '.$account->secret('secret_key')])->acceptJson()
            ->post(self::BASE.'/acceptance/void_refund/'.($isVoid ? 'void' : 'refund'), $body);

        $status = $response->status();
        if ($response->successful()) {
            $id = data_get($response->json(), 'id');

            return new RefundOutcome(RefundOutcome::ACCEPTED, is_scalar($id) ? (string) $id : null, 'Accepted by Paymob (HTTP '.$status.'); awaiting confirmation.', ['http' => $status]);
        }
        if (in_array($status, [400, 401, 403, 404, 422], true)) {
            $msg = (string) (data_get($response->json(), 'message') ?? data_get($response->json(), 'detail') ?? data_get($response->json(), 'error') ?? '');

            return new RefundOutcome(RefundOutcome::REJECTED, null, 'Paymob refused the request (HTTP '.$status.')'.($msg !== '' ? ': '.mb_substr(preg_replace('/\s+/', ' ', strip_tags($msg)), 0, 160) : '.'), ['http' => $status]);
        }

        // 5xx / 429 / anything else: the provider may or may not have acted.
        return new RefundOutcome(RefundOutcome::UNKNOWN, null, 'Paymob answered HTTP '.$status.'; the result is unknown.', ['http' => $status]);
    }

    public function inquire(PaymentRefund $refund, PaymentAttempt $attempt): RefundOutcome
    {
        $account = $attempt->integrationAccount;
        try {
            // 1) the refund's own transaction, when Paymob gave us its id
            if (filled($refund->provider_refund_id) && ctype_digit((string) $refund->provider_refund_id)) {
                $tx = $this->paymob->inquireTransaction(null, (string) $refund->provider_refund_id, $account);
                $success = filter_var(data_get($tx, 'success'), FILTER_VALIDATE_BOOLEAN);
                $pending = filter_var(data_get($tx, 'pending'), FILTER_VALIDATE_BOOLEAN);
                $child = filter_var(data_get($tx, 'has_parent_transaction'), FILTER_VALIDATE_BOOLEAN);
                $amount = data_get($tx, 'amount_cents');

                if ($child && $success && ! $pending && is_numeric($amount) && (int) $amount === $refund->amount_minor) {
                    return new RefundOutcome(RefundOutcome::SUCCEEDED, (string) $refund->provider_refund_id, 'Refund transaction confirmed by Paymob.', ['source' => 'refund_transaction', 'amount_cents' => (int) $amount]);
                }
                if ($child && ! $success && ! $pending) {
                    return new RefundOutcome(RefundOutcome::FAILED, (string) $refund->provider_refund_id, 'Paymob reports the refund transaction as unsuccessful.', ['source' => 'refund_transaction']);
                }

                return new RefundOutcome(RefundOutcome::PENDING, (string) $refund->provider_refund_id, 'Refund still processing at Paymob.');
            }

            // 2) otherwise the original transaction: only the documented is_refunded / is_voided flags, and only where they are unambiguous
            $orig = $this->paymob->inquireTransaction(null, (string) $attempt->transaction_id, $account);
            if ($refund->type === 'void' && filter_var(data_get($orig, 'is_voided'), FILTER_VALIDATE_BOOLEAN)) {
                return new RefundOutcome(RefundOutcome::SUCCEEDED, null, 'Transaction reported as voided by Paymob.', ['source' => 'original_transaction']);
            }
            if ($refund->type === 'full' && filter_var(data_get($orig, 'is_refunded'), FILTER_VALIDATE_BOOLEAN)) {
                return new RefundOutcome(RefundOutcome::SUCCEEDED, null, 'Transaction reported as refunded by Paymob.', ['source' => 'original_transaction']);
            }

            return new RefundOutcome(RefundOutcome::UNKNOWN, null, 'No unambiguous confirmation available; check the Paymob dashboard.');
        } catch (ConnectionException|\Throwable $e) {
            return new RefundOutcome(RefundOutcome::UNKNOWN, null, 'Paymob could not be reached ('.class_basename($e).').');
        }
    }
}
