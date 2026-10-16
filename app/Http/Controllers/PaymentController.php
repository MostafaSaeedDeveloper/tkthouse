<?php

namespace App\Http\Controllers;

use App\Models\Order;
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

        $methods = $gateways->available();
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

        $paymentMethod = $gateways->available()->firstWhere('code', (string) $request->query('method', $order->payment_method));

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
    public function paymobCallback(Request $request, PaymentService $payments)
    {
        $payload = $request->all();
        $merchantOrderId = (string) data_get($payload, 'obj.order.merchant_order_id', data_get($payload, 'merchant_order_id', ''));
        $transactionId = (string) data_get($payload, 'obj.id', data_get($payload, 'id', ''));
        $isGet = $request->isMethod('get');

        $secret = (string) (config('payments.paymob.hmac_secret') ?: '');
        $hmac = (string) ($request->query('hmac') ?: data_get($payload, 'hmac', ''));
        $signatureRequired = PaymentEnvironment::paymobSignatureRequired();

        if ($signatureRequired && $secret === '') {
            // Fail closed: in production an unconfigured secret means NOTHING is accepted (and ops must hear about it).
            Log::critical('Paymob callback rejected: PAYMOB_HMAC_SECRET is not configured but signatures are mandatory.');

            return $isGet
                ? redirect()->route('front.account.orders')->with('error', 'Payment confirmation is temporarily unavailable. If you were charged, contact support.')
                : response()->json(['received' => false, 'updated' => false], 503);
        }

        $signed = $secret !== '' && (
            $isGet
                ? PaymobService::hmacIsValidFlat($request->query(), $hmac, $secret)
                : (is_array(data_get($payload, 'obj')) && PaymobService::hmacIsValid($payload['obj'], $hmac, $secret))
        );

        // Server-to-server webhook: a missing/invalid signature is refused outright whenever a secret exists.
        if (! $isGet && $secret !== '' && ! $signed) {
            Log::warning('Paymob callback rejected: invalid HMAC.', ['merchant_order_id' => $merchantOrderId]);

            return response()->json(['received' => false, 'updated' => false], 403);
        }

        // Browser return: the id in the URL is only used as a lookup key when the URL is signed (or signatures are
        // optional in this environment). Settlement itself always goes through the server-to-server inquiry.
        $hintAllowed = $signed || ($secret === '' && ! $signatureRequired);

        $order = $merchantOrderId !== '' ? $this->findOrderFromMerchantOrderId($merchantOrderId) : null;

        if (! $order) {
            Log::warning('Paymob callback order not found.', ['merchant_order_id' => $merchantOrderId]);

            return $isGet
                ? redirect()->route('front.account.orders')->with('error', 'We could not find that payment. If you were charged, contact support.')
                : response()->json(['received' => true, 'updated' => false]);
        }

        $outcome = $payments->refresh($order, $hintAllowed && $transactionId !== '' ? $transactionId : null, 'paymob');
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

    /** Shared checks for pages that act on a payable order. Returns a redirect when the order isn't payable. */
    private function guard(Request $request, Order $order, string $token)
    {
        if ($order->status === 'pending_payment'
            && app(PendingPaymentExpiryService::class)->expireOrderIfNeeded($order->loadMissing('customer'), SystemSettings::pendingPaymentTimeoutMinutes())) {
            return redirect()->route('front.checkout.confirmation', $order->order_number);
        }

        abort_unless($request->user() && (int) $order->user_id === (int) $request->user()->id, 403);
        abort_unless($order->payment_link_token && hash_equals($order->payment_link_token, $token), 404);

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
}
