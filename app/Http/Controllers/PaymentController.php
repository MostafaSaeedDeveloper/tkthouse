<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Auth\GuestOrderAccess;
use App\Services\PaymobService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use App\Services\PendingPaymentExpiryService;
use App\Support\PaymentEnvironment;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * Paying an existing order: payment page (retry), hand-off to the gateway, and the gateway callbacks.
 * Payment status is only ever decided by PaymentService after asking the gateway server-to-server.
 */
class PaymentController extends Controller
{
    public function show(Request $request, Order $order, string $token, PaymentGatewayManager $gateways)
    {
        if ($redirect = $this->guard($request, $order, $token)) {
            return $redirect;
        }

        $methods = $this->methodsFor($order, $gateways);
        $selected = $methods->firstWhere('code', $order->payment_method)?->code ?? $methods->first()?->code ?? $order->payment_method;

        $order->load(['items', 'customer', 'paymentAttempts']);

        return view('front.checkout.payment', [
            'order' => $order,
            'methods' => $methods,
            'selectedMethod' => $selected,
            'event' => \App\Models\Event::query()->find($order->items->pluck('event_id')->filter()->first()),
            'lastFailure' => $order->paymentAttempts->where('status', 'failed')->sortByDesc('id')->first(),
        ]);
    }

    public function gateway(Request $request, Order $order, string $token, PaymentService $payments, PaymentGatewayManager $gateways)
    {
        if ($redirect = $this->guard($request, $order, $token)) {
            return $redirect;
        }

        $paymentMethod = $this->methodsFor($order, $gateways)->firstWhere('code', (string) $request->query('method', $order->payment_method));

        if (! $paymentMethod || ! $gateways->isOnline($paymentMethod)) {
            return redirect()->route('front.orders.payment', ['order' => $order, 'token' => $token])
                ->withErrors(['payment' => 'Selected payment method is not available for online payment.']);
        }

        if ($order->payment_method !== $paymentMethod->code) {
            $order->update(['payment_method' => $paymentMethod->code]);
        }

        try {
            $url = $payments->start($order, $paymentMethod);

            $this->log($order, $request->user(), 'Payment gateway opened', [
                'provider' => $paymentMethod->provider, 'payment_method' => $paymentMethod->code,
                'payment_status' => 'opened', 'transaction_url' => $url,
            ]);
        } catch (\Throwable $exception) {
            $this->log($order, $request->user(), 'Payment attempt failed', [
                'provider' => $paymentMethod->provider, 'payment_method' => $paymentMethod->code,
                'payment_status' => 'failed', 'failure_reason' => $exception->getMessage(),
            ]);
            Log::warning('Could not open payment gateway.', ['order_id' => $order->id, 'error' => $exception->getMessage()]);

            return redirect()->route('front.orders.payment', ['order' => $order, 'token' => $token])
                ->withErrors(['payment' => 'We could not open the payment page right now. Please try again or choose another method.']);
        }

        return redirect()->away($url);
    }

    /**
     * Paymob return (GET) and processed callback (POST). Whatever the body says, the order is only settled
     * after the transaction has been looked up at Paymob.
     */
    public function paymobCallback(Request $request, PaymentService $payments, \App\Services\Payments\Webhooks\WebhookService $webhooks)
    {
        $payload = $request->all();
        $merchantOrderId = (string) data_get($payload, 'obj.order.merchant_order_id', data_get($payload, 'merchant_order_id', ''));
        $transactionId = (string) data_get($payload, 'obj.id', data_get($payload, 'id', ''));
        $isGet = $request->isMethod('get');
        $obj = $isGet ? $request->query() : (array) data_get($payload, 'obj', []);

        // Which merchant account does this callback belong to? Looked up from OUR unique merchant_order_id (attempt reference). The
        // field is unauthenticated, so it only selects which secret to verify with: a wrong pick fails the signature check, and
        // settlement still needs the server-to-server inquiry with that account's own API key.
        $attempt = $merchantOrderId !== ''
            ? \App\Models\PaymentAttempt::query()->where('provider', 'paymob')->where('reference', $merchantOrderId)->first()
            : null;
        $account = $attempt?->integration_account_id ? $attempt->integrationAccount : null;

        $secret = $account ? (string) ($account->secret('hmac_secret') ?: '') : (string) (config('payments.paymob.hmac_secret') ?: '');
        $hmac = (string) ($request->query('hmac') ?: data_get($payload, 'hmac', ''));
        $signatureRequired = PaymentEnvironment::paymobSignatureRequired();

        $signed = $secret !== '' && (
            $isGet
                ? PaymobService::hmacIsValidFlat($request->query(), $hmac, $secret)
                : (is_array(data_get($payload, 'obj')) && PaymobService::hmacIsValid($payload['obj'], $hmac, $secret))
        );
        $signature = $secret === '' ? 'unverifiable' : ($hmac === '' ? 'missing' : ($signed ? 'valid' : 'invalid'));

        // Event type from the (unauthenticated until proven) body — informational; money state comes from the inquiry.
        $flag = fn ($k) => filter_var(data_get($obj, $k), FILTER_VALIDATE_BOOLEAN);
        $type = match (true) {
            $flag('is_voided') => 'void',
            $flag('is_refunded') || $flag('has_parent_transaction') => 'refund',
            $flag('pending') => 'payment.pending',
            $flag('success') => 'payment.succeeded',
            default => 'payment.failed',
        };
        [$event] = $webhooks->record('paymob', $type, [$transactionId, $type, data_get($obj, 'amount_cents'), $isGet ? 'get' : 'post'], $transactionId ?: $merchantOrderId, $signature, (array) $obj, $account, $attempt);

        if ($signatureRequired && $secret === '') {
            // Fail closed: in production an unconfigured secret means NOTHING is accepted (and ops must hear about it).
            Log::critical('Paymob callback rejected: HMAC secret is not configured but signatures are mandatory.');
            $webhooks->finish($event, 'failed', 'HMAC secret not configured');

            return $isGet
                ? redirect()->route('front.account.orders')->with('error', 'Payment confirmation is temporarily unavailable. If you were charged, contact support.')
                : response()->json(['received' => false, 'updated' => false], 503);
        }

        // Server-to-server webhook: a missing/invalid signature is refused outright whenever a secret exists.
        if (! $isGet && $secret !== '' && ! $signed) {
            Log::warning('Paymob callback rejected: invalid HMAC.', ['merchant_order_id' => $merchantOrderId]);
            $webhooks->finish($event, 'failed', 'Invalid signature');

            return response()->json(['received' => false, 'updated' => false], 403);
        }

        // Browser return: the id in the URL is only used as a lookup key when the URL is signed (or signatures are
        // optional in this environment). Settlement itself always goes through the server-to-server inquiry.
        $hintAllowed = $signed || ($secret === '' && ! $signatureRequired);

        $order = $attempt?->order ?? ($merchantOrderId !== '' ? $this->findOrderFromMerchantOrderId($merchantOrderId) : null);

        if (! $order) {
            Log::warning('Paymob callback order not found.', ['merchant_order_id' => $merchantOrderId]);
            $webhooks->finish($event, 'ignored', 'No matching order');

            return $isGet
                ? redirect()->route('front.account.orders')->with('error', 'We could not find that payment. If you were charged, contact support.')
                : response()->json(['received' => true, 'updated' => false]);
        }

        $hint = $hintAllowed && $transactionId !== '' ? $transactionId : null;

        if (in_array($type, ['refund', 'void'], true)) {
            // Refund/void notifications are verified and applied by the refund module, never by the payment settlement path.
            $outcome = app(\App\Services\Payments\Webhooks\WebhookService::class)->retry($event);
        } else {
            // An identified attempt is settled alone (with its own account); only legacy callbacks fall back to the order-wide refresh.
            $outcome = $attempt ? $payments->refreshAttempt($attempt, $hint) : $payments->refresh($order, $hint, 'paymob');
            $webhooks->finish($event, 'processed');
        }
        Log::info('Paymob callback processed.', ['order_id' => $order->id, 'outcome' => $outcome]);

        if ($isGet) {
            return redirect()->route('front.checkout.confirmation', $order->order_number);
        }

        return response()->json(['received' => true, 'updated' => in_array($outcome, [PaymentService::OUTCOME_PAID, PaymentService::OUTCOME_ALREADY_PAID], true)]);
    }

    /** Fawaterak redirect: `result` is only a hint for the landing screen; the invoice is looked up at Fawaterak. */
    public function fawaterakCallback(Request $request, Order $order, string $token, PaymentService $payments)
    {
        abort_unless($order->payment_link_token && hash_equals($order->payment_link_token, $token), 404);

        $hint = trim((string) ($request->query('invoice_id') ?: $request->query('payment_id') ?: $request->query('transaction_id') ?: $request->query('id')));

        $payments->refresh($order, $hint !== '' ? $hint : null, 'fawaterak');

        return redirect()->route('front.checkout.confirmation', $order->order_number);
    }

    /**
     * Shared checks for pages that act on a payable order. Returns a response when the order can't be paid right now.
     * Access = the link token AND (signed-in owner OR the browser session that placed the order). Anyone else who has a
     * valid emailed link is asked to prove they own the account's mailbox with a one-time code.
     */
    private function guard(Request $request, Order $order, string $token)
    {
        abort_unless($order->payment_link_token && hash_equals($order->payment_link_token, $token), 404);

        if (! GuestOrderAccess::can($request, $order)) {
            $order->loadMissing('user');
            abort_unless($order->user, 403);

            return response()->view('front.checkout.verify-link', [
                'order' => $order,
                'token' => $token,
                'emailMasked' => GuestOrderAccess::mask($order->user->email),
                'email' => '', // never hand the account address to someone who only holds the link
            ], 401);
        }

        if ($order->status === 'pending_payment'
            && app(PendingPaymentExpiryService::class)->expireOrderIfNeeded($order->loadMissing('customer'), SystemSettings::pendingPaymentTimeoutMinutes())) {
            return redirect()->route('front.checkout.confirmation', $order->order_number);
        }

        if ($order->status === 'paid') {
            return redirect()->route('front.checkout.confirmation', $order->order_number);
        }

        abort_unless($order->status === 'pending_payment', 404);

        return null;
    }

    private function findOrderFromMerchantOrderId(string $merchantOrderId): ?Order
    {
        $direct = Order::query()->where('order_number', $merchantOrderId)->first();
        if ($direct) {
            return $direct;
        }

        $base = trim((string) strtok($merchantOrderId, '-'));

        return $base !== '' ? Order::query()->where('order_number', $base)->first() : null;
    }

    private function log(Order $order, $causer, string $description, array $properties = []): Activity
    {
        $logger = activity('orders')->performedOn($order)->withProperties($properties);
        if ($causer) {
            $logger->causedBy($causer);
        }

        return $logger->log($description);
    }

    /** Methods for an existing order: the pre-existing list unless routing/default account/policy says otherwise. */
    private function methodsFor(Order $order, PaymentGatewayManager $gateways)
    {
        return app(\App\Services\Payments\Routing\OfferedMethods::class)->for(app(\App\Services\Payments\Routing\PaymentAccountResolver::class)->forOrder($order));
    }
}
