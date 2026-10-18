<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationAccount;
use App\Models\WebhookEvent;
use App\Services\Payments\Webhooks\WebhookService;
use Illuminate\Http\Request;

class WebhookAdminController extends Controller
{
    /** Provider facts shown to operators. "Configured" = an operator says the URL is in the provider portal; "Verified" = we received an authentic event. */
    private function providers(): array
    {
        return [
            'paymob' => [
                'label' => 'Paymob', 'url' => route('front.paymob.callback'),
                'events' => ['Transaction processed callback (success / failure / pending / refund / void) — POST, signed with HMAC-SHA512', 'Transaction response callback (customer return) — GET, signed'],
                'note' => 'Set this URL for both callbacks in each Paymob integration. The HMAC secret is stored per merchant account.',
            ],
            'fawaterak' => [
                'label' => 'Fawaterak', 'url' => route('front.fawaterak.webhook'),
                'events' => ['Paid (a trigger only: the payment is always confirmed by asking Fawaterak directly; no vendor key needed)', 'Failed (no hash documented: informational)', 'Cancel / expired — Fawry, Aman, Masary (informational)', 'Refund (no hash documented: informational, approval only)'],
                'note' => 'Add the URL in the Fawaterak portal → Integration. It must end with _json (this one does). A vendor key is optional (it only lets us check the webhook signature).',
            ],
        ];
    }

    public function index(Request $request)
    {
        $providers = $this->providers();
        $accounts = IntegrationAccount::query()->whereIn('provider', array_keys($providers))->orderBy('provider')->orderBy('name')->get();

        $stats = [];
        foreach ($accounts as $a) {
            $base = WebhookEvent::query()->where('integration_account_id', $a->id);
            $stats[$a->id] = [
                'last' => (clone $base)->latest('received_at')->value('received_at'),
                'ok' => (clone $base)->where('processing_status', 'processed')->where('signature_status', 'valid')->latest('received_at')->value('received_at'),
                'fail' => (clone $base)->where('processing_status', 'failed')->latest('received_at')->value('received_at'),
                'verified' => (clone $base)->where('signature_status', 'valid')->exists(),
            ];
        }
        $providerStats = [];
        foreach (array_keys($providers) as $p) {
            $b = WebhookEvent::query()->where('provider', $p);
            $providerStats[$p] = ['last' => (clone $b)->latest('received_at')->value('received_at'), 'ok' => (clone $b)->where('processing_status', 'processed')->latest('received_at')->value('received_at'), 'fail' => (clone $b)->where('processing_status', 'failed')->latest('received_at')->value('received_at')];
        }

        $events = WebhookEvent::query()->with(['account:id,name', 'attempt:id,order_id,internal_reference'])
            ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->string('provider')))
            ->when($request->filled('status'), fn ($q) => $q->where('processing_status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('event_type', $request->string('type')))
            ->when($request->filled('q'), fn ($q) => $q->where('transaction_reference', 'like', '%'.$request->string('q').'%'))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.payments.webhooks', [
            'providers' => $providers, 'accounts' => $accounts, 'stats' => $stats, 'providerStats' => $providerStats, 'events' => $events,
            'canRetry' => $request->user()->can('payments.transactions.inquire'),
            'canConfigure' => $request->user()->can('payments.accounts.manage') || $request->user()->can('settings.payment-accounts.manage'),
        ]);
    }

    public function retry(Request $request, WebhookEvent $event, WebhookService $webhooks)
    {
        abort_unless($event->order_id === null || \App\Models\Order::query()->visibleTo($request->user())->whereKey($event->order_id)->exists(), 404);
        try {
            $outcome = $webhooks->retry($event);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        activity('settings')->causedBy($request->user())->withProperties(['event_id' => $event->id, 'provider' => $event->provider, 'outcome' => $outcome])->log('Webhook event reprocessed');

        return back()->with('success', 'Event reprocessed ('.str_replace('_', ' ', (string) $outcome).').');
    }

    public function markConfigured(Request $request, IntegrationAccount $account)
    {
        abort_unless(in_array($account->provider, ['paymob', 'fawaterak'], true), 404);
        $account->forceFill(['webhook_configured_at' => now(), 'webhook_configured_by' => $request->user()->id])->save();
        activity('settings')->causedBy($request->user())->performedOn($account)->log('Webhook URL confirmed as configured in the provider portal');

        return back()->with('success', 'Marked as configured. It becomes "Verified" only after an authentic event is received.');
    }
}
