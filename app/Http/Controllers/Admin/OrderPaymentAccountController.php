<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationAccount;
use App\Models\Order;
use App\Orders\OrderAccess;
use App\Services\Payments\Routing\PaymentAccountSwitcher;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderPaymentAccountController extends Controller
{
    /** Explicit merchant-account switch. Permission: orders.reroute_payment; the order must also be visible/manageable to the actor. */
    public function switch(Request $request, Order $order, PaymentAccountSwitcher $switcher)
    {
        $user = $request->user();
        abort_unless($order->isVisibleTo($user), 404);
        abort_unless(OrderAccess::canManage($order, $user), 403);

        $data = $request->validate([
            'integration_account_id' => ['required', 'integer', 'exists:integration_accounts,id'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ]);

        try {
            $switcher->switch($order, IntegrationAccount::query()->findOrFail($data['integration_account_id']), $user, $data['reason']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Payment account switched. Earlier payment sessions stay under their original account and are still reconciled.');
    }
}
