<?php

/** Child process used by ConcurrencyMysqlTest: runs ONE routing operation against the shared (committed) test database. */

use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payments\Routing\CheckoutRoutingService;
use App\Services\Payments\Routing\ReservationInvalid;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$script, $mode, $a, $b, $c, $d, $e] = array_pad($argv, 7, null);
$svc = app(CheckoutRoutingService::class);

$newOrder = function (): Order {
    $c = Customer::firstOrCreate(['email' => 'w@example.test'], ['first_name' => 'W', 'last_name' => 'W', 'phone' => '0100']);

    return Order::create(['customer_id' => $c->id, 'order_number' => (string) random_int(1000000000, 9999999999), 'status' => 'pending_payment', 'payment_method' => 'fake_card', 'total_amount' => 100]);
};

try {
    if ($mode === 'reserve') {                 // reserve only; prints "agent|token"
        $r = $svc->reserve(null, 'session-'.$a);
        echo $r->assignee_id.'|'.$r->token;
    } elseif ($mode === 'place') {             // reserve + place an order (consume/finalize in one transaction)
        $r = $svc->reserve(null, 'session-'.$a);
        $out = DB::transaction(function () use ($svc, $r, $newOrder) {
            $d = $svc->consume($r->token, 'fake_card', true);
            $o = $newOrder();
            $svc->finalize($d, $o);

            return $o->fresh()->assigned_user_id;
        });
        echo 'OK:'.$out;
    } elseif ($mode === 'consume') {           // many workers race to consume ONE token
        $out = DB::transaction(function () use ($svc, $a, $newOrder) {
            $d = $svc->consume($a, 'fake_card', true);
            $o = $newOrder();
            $svc->finalize($d, $o);

            return $o->id;
        });
        echo 'OK:'.$out;
    } elseif ($mode === 'refund') {            // refund request race: attempt, user, type, amount, idempotency key
        $r = app(\App\Services\Payments\Refunds\RefundService::class)->request(\App\Models\PaymentAttempt::findOrFail((int) $a), \App\Models\User::findOrFail((int) $b), $c, $d === '-' ? null : $d, 'concurrency probe refund', $e);
        echo 'OK:'.$r->id;
    } elseif ($mode === 'sell') {              // a real checkout placement of ONE unit of a ticket type: event id, ticket id, user id
        $event = \App\Models\Event::findOrFail((int) $a);
        $units = app(\App\Services\Checkout\CheckoutSelection::class)->build($event, collect([['ticket_id' => (int) $b, 'qty' => 1]]))['units'];
        $attendee = ['name' => 'Fan', 'email' => 'fan@example.test', 'phone' => '+201000000001', 'gender' => 'male'];
        $o = app(\App\Services\Checkout\OrderPlacementService::class)->place(\App\Models\User::findOrFail((int) $c), $event, collect($units), ['first_name' => 'B', 'last_name' => 'W', 'email' => 'bw@example.test', 'phone' => '+201000000002'], [$attendee], null, 'fake_card');
        echo 'OK:'.$o->id;
    } elseif ($mode === 'remove-ticket') {     // the event form explicitly removing a ticket type: event id, ticket id
        $r = app(\App\Services\Events\EventTicketSync::class)->sync(\App\Models\Event::findOrFail((int) $a), [], [(int) $b]);
        echo 'OK:'.json_encode($r);
    } elseif ($mode === 'ext-import') {        // import-by-id race: account id, provider transaction id (fake provider)
        $t = app(\App\Services\Payments\External\ExternalTransactionService::class)->lookup(\App\Models\IntegrationAccount::findOrFail((int) $a), (string) $b);
        echo 'OK:'.$t->id;
    } elseif ($mode === 'delete-event') {      // permanent delete attempt: event id
        app(\App\Services\Events\EventLifecycle::class)->delete(\App\Models\Event::findOrFail((int) $a));
        echo 'OK:deleted';
    } elseif ($mode === 'archive-event') {     // archive: event id
        $e = app(\App\Services\Events\EventLifecycle::class)->archive(\App\Models\Event::findOrFail((int) $a));
        echo 'OK:'.$e->status;
    } elseif ($mode === 'approve') {           // approval race: refund id, approver user id
        $r = app(\App\Services\Payments\Refunds\RefundService::class)->approve(\App\Models\PaymentRefund::findOrFail((int) $a), \App\Models\User::findOrFail((int) $b));
        echo 'OK:'.$r->status;
    } elseif ($mode === 'submit') {            // reconciliation / retry racing the approval: must never send before it
        app(\App\Services\Payments\Refunds\RefundService::class)->submit(\App\Models\PaymentRefund::findOrFail((int) $a));
        echo 'OK:'.\App\Models\PaymentRefund::findOrFail((int) $a)->status;
    } elseif ($mode === 'refund-confirm') {    // confirmation race: refund id, user id
        $svc = app(\App\Services\Payments\Refunds\RefundService::class);
        $r = $svc->confirmSucceeded(\App\Models\PaymentRefund::findOrFail((int) $a), \App\Models\User::findOrFail((int) $b), 'EXT-'.$c, 'portal screenshot verified', now()->toDateString());
        echo 'OK:'.$r->status;
    } elseif ($mode === 'refund-fail') {       // "it did not happen" racing a confirmation: refund id, user id
        $r = app(\App\Services\Payments\Refunds\RefundService::class)->confirmFailed(\App\Models\PaymentRefund::findOrFail((int) $a), \App\Models\User::findOrFail((int) $b), 'portal shows it failed');
        echo 'OK:'.$r->status;
    } elseif ($mode === 'refund-tickets') {    // ticket claim race: attempt id (0 = legacy order), order id, user id, comma list of ticket ids, idempotency key
        $svc = app(\App\Services\Payments\Refunds\RefundService::class);
        $opts = ['tickets' => array_map('intval', explode(',', (string) $d))];
        $by = \App\Models\User::findOrFail((int) $c);
        $r = (int) $a > 0
            ? $svc->request(PaymentAttempt::findOrFail((int) $a), $by, 'partial', null, 'concurrency ticket claim', $e, $opts)
            : $svc->requestForOrder(Order::findOrFail((int) $b), $by, 'partial', null, 'concurrency ticket claim', $e, $opts);
        echo 'OK:'.$r->id;
    } elseif ($mode === 'issue') {             // what every payment callback / reconcile / admin save runs: order id
        app(\App\Services\TicketIssuanceService::class)->issueIfPaid(Order::withTrashed()->findOrFail((int) $a));
        echo 'OK:issued';
    } elseif ($mode === 'tx') {                // many workers race to bind the same gateway transaction id
        PaymentAttempt::create(['order_id' => (int) $a, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 100, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => $b]);
        echo 'OK';
    }
} catch (Illuminate\Validation\ValidationException $e) {
    echo 'INVALID:'.(collect($e->errors())->flatten()->first() ?? 'validation');
} catch (ReservationInvalid $e) {
    echo 'INVALID:'.$e->reason.(getenv('WORKER_DEBUG') ? ' fake='.var_export(config('payments.fake.enabled'), true).' env='.app()->environment().' routing='.var_export(\App\Services\Payments\Routing\PaymentAccountResolver::routingEnabled(), true).' tok='.($a ?? '') : '');
} catch (Illuminate\Database\UniqueConstraintViolationException $e) {
    echo 'DUP';
} catch (Throwable $e) {
    echo 'ERR:'.get_class($e).':'.$e->getMessage();
}
