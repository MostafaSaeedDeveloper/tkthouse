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

[$script, $mode, $a, $b] = array_pad($argv, 4, null);
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
    } elseif ($mode === 'tx') {                // many workers race to bind the same gateway transaction id
        PaymentAttempt::create(['order_id' => (int) $a, 'provider' => 'paymob', 'method_code' => 'paymob_card', 'amount' => 100, 'currency' => 'EGP', 'status' => 'paid', 'transaction_id' => $b]);
        echo 'OK';
    }
} catch (ReservationInvalid $e) {
    echo 'INVALID:'.$e->reason.(getenv('WORKER_DEBUG') ? ' fake='.var_export(config('payments.fake.enabled'), true).' env='.app()->environment().' routing='.var_export(\App\Services\Payments\Routing\PaymentAccountResolver::routingEnabled(), true).' tok='.($a ?? '') : '');
} catch (Illuminate\Database\UniqueConstraintViolationException $e) {
    echo 'DUP';
} catch (Throwable $e) {
    echo 'ERR:'.get_class($e).':'.$e->getMessage();
}
