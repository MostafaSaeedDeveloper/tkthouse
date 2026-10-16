<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

/** Stand-in for a hosted payment page, available only where the fake gateway is enabled (never in production). */
class PaymentSimulatorController extends Controller
{
    private function authorizeAttempt(Request $request, PaymentAttempt $attempt): void
    {
        abort_unless(\App\Support\PaymentEnvironment::fakeGatewayAllowed(), 404);
        abort_unless($attempt->provider === 'fake', 404);
        abort_unless(\App\Services\Auth\GuestOrderAccess::can($request, $attempt->order), 403);
    }

    public function show(Request $request, PaymentAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);

        return view('front.checkout.fake-gateway', ['attempt' => $attempt->load('order')]);
    }

    public function complete(Request $request, PaymentAttempt $attempt, PaymentService $payments)
    {
        $this->authorizeAttempt($request, $attempt);

        $outcome = $request->validate(['outcome' => ['required', 'in:paid,failed,pending']])['outcome'];

        $attempt->update(['meta' => array_merge($attempt->meta ?? [], ['fake_state' => $outcome])]);

        // Same path a real gateway callback takes.
        $payments->refresh($attempt->order, null, 'fake');

        return redirect()->route('front.checkout.confirmation', $attempt->order->order_number);
    }
}
