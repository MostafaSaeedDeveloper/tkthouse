<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\Definitions;
use App\Integrations\IntegrationManager;
use App\Models\IntegrationAccount;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function __construct(private readonly IntegrationManager $manager)
    {
    }

    public const PAYMENT_PROVIDERS = ['paymob', 'fawaterak', 'fake'];

    /** Non-financial integrations (WhatsApp, SMTP, ...). Payment providers live under Settings → Payments. */
    public function index(Request $request)
    {
        if (in_array($request->query('provider'), self::PAYMENT_PROVIDERS, true)) {
            return redirect()->route('admin.payments.providers', ['provider' => $request->query('provider')]); // old bookmarks
        }

        return $this->render('messaging');
    }

    /** Providers & Merchant Accounts (Paymob, Fawaterak, ...): the same accounts, managed in one place. */
    public function providers(Request $request)
    {
        return $this->render('payment');
    }

    private function render(string $kind)
    {
        $request = request();
        $accounts = IntegrationAccount::query()->orderBy('provider')->orderBy('name')->get()->groupBy('provider');
        $methods = \App\Models\PaymentMethod::query()->where('is_active', true)->orderBy('id')->get(['code', 'name', 'provider'])->groupBy('provider');

        $defaultId = (int) \App\Support\SystemSettings::get('default_payment_account_id', 0);

        return view('admin.settings.integrations', [
            'kind' => $kind,
            'defaultAccountId' => $defaultId,
            'definitions' => array_filter(Definitions::all(), fn ($d) => $d['kind'] === $kind),
            'accounts' => $accounts,
            'methodsByProvider' => $methods,
            'canManage' => $kind === 'payment' ? $this->canAny($request->user(), ['payments.accounts.manage', 'settings.integrations.manage']) : $request->user()->can('settings.integrations.manage'),
            'canTest' => $kind === 'payment' ? $this->canAny($request->user(), ['payments.accounts.manage', 'settings.integrations.test']) : $request->user()->can('settings.integrations.test'),
        ]);
    }

    public function store(Request $request, string $provider)
    {
        abort_unless(Definitions::get($provider), 404);
        $this->guard($provider, 'manage');
        $request->validate([
            'name' => ['required', 'string', 'max:120', \Illuminate\Validation\Rule::unique('integration_accounts', 'name')->where('provider', $provider)],
            'environment' => ['required', 'in:sandbox,production'],
        ]);

        $account = $this->manager->save(null, $provider, $request->all(), $request->user());

        return $this->backTo($account)->with('success', "Account \"{$account->name}\" created. Run a connection test before enabling it.");
    }

    public function update(Request $request, IntegrationAccount $account)
    {
        $this->guard($account->provider, 'manage');
        $request->validate([
            'name' => ['required', 'string', 'max:120', \Illuminate\Validation\Rule::unique('integration_accounts', 'name')->where('provider', $account->provider)->ignore($account->id)],
            'environment' => ['required', 'in:sandbox,production'],
        ]);

        $this->manager->save($account, $account->provider, $request->all(), $request->user());

        return $this->backTo($account ?? null)->with('success', 'Account saved. If credentials changed it was disabled and must be re-tested.');
    }

    public function test(Request $request, IntegrationAccount $account)
    {
        $this->guard($account->provider, 'test');
        $recipient = null;
        if ($account->provider === 'email' && $request->filled('recipient')) {
            $recipient = $request->validate(['recipient' => ['required', 'email:rfc', 'max:190']])['recipient'];
        }
        $result = $this->manager->test($account, $request->user(), $recipient);

        return $this->backTo($account ?? null)->with($result['verified'] ? 'success' : ($result['ok'] ? 'success' : 'error'), $account->name.': '.$result['message']);
    }

    public function enable(Request $request, IntegrationAccount $account)
    {
        $this->guard($account->provider, 'manage');
        $this->manager->enable($account, $request->boolean('confirm_production'), $request->user());

        return $this->backTo($account ?? null)->with('success', "\"{$account->name}\" is now enabled.");
    }

    public function disable(Request $request, IntegrationAccount $account)
    {
        $this->guard($account->provider, 'manage');
        $this->manager->disable($account, $request->user());

        return $this->backTo($account ?? null)->with('success', "\"{$account->name}\" was disabled.");
    }

    public function attest(Request $request, IntegrationAccount $account)
    {
        $this->guard($account->provider, 'manage');
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:300']]);
        $this->manager->attestSandbox($account, $data['note'], $request->user());

        return $this->backTo($account ?? null)->with('success', 'Sandbox verification recorded for the current credentials.');
    }

    public function refundVerified(Request $request, IntegrationAccount $account)
    {
        $this->guard($account->provider, 'manage');
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:300'], 'on' => ['required', 'boolean']]);
        try {
            $this->manager->setRefundVerified($account, (bool) $data['on'], $data['note'], $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', $data['on'] ? 'Refunds confirmed for this account (the environment flag PAYMENTS_REFUNDS_LIVE is still required).' : 'Refunds disabled for this account.');
    }

    public function archive(Request $request, IntegrationAccount $account)
    {
        $this->guard($account->provider, 'manage');
        $this->manager->archive($account, $request->user());

        return $this->backTo($account ?? null)->with('success', "\"{$account->name}\" archived. Its payment history stays verifiable.");
    }

    public function destroy(Request $request, IntegrationAccount $account)
    {
        $this->guard($account->provider, 'manage');
        abort_if($account->enabled, 422, 'Disable the account before deleting it.');
        if ($account->isReferenced()) {
            return back()->with('error', 'This account has payment history, orders, users or reservations attached and cannot be deleted. Archive it instead.');
        }
        activity('settings')->causedBy($request->user())->withProperties(['provider' => $account->provider, 'name' => $account->name])->log('Integration account deleted');
        $account->delete();
        $this->manager->flush();

        return $this->backTo($account ?? null)->with('success', 'Account deleted.');
    }

    /** Payment providers: payments.accounts.* (or the Stage E equivalents). Other integrations keep settings.integrations.*. */
    private function guard(?string $provider, string $ability): void
    {
        $user = request()->user();
        if (in_array($provider, self::PAYMENT_PROVIDERS, true)) {
            $ok = $ability === 'test'
                ? $this->canAny($user, ['payments.accounts.manage', 'settings.integrations.test'])
                : $this->canAny($user, ['payments.accounts.manage', 'settings.integrations.manage']);
        } else {
            $ok = $user->can($ability === 'test' ? 'settings.integrations.test' : 'settings.integrations.manage');
        }
        abort_unless($ok, 403);
    }

    private function canAny($user, array $perms): bool
    {
        foreach ($perms as $p) {
            if ($user->can($p)) {
                return true;
            }
        }

        return false;
    }

    private function backTo(?IntegrationAccount $account = null, ?string $provider = null)
    {
        $provider ??= $account?->provider;

        return redirect()->route(in_array($provider, self::PAYMENT_PROVIDERS, true) ? 'admin.payments.providers' : 'admin.integrations.index');
    }

    /** One single default merchant account (the Payment Routing setting): shown and set here, stored once. */
    public function makeDefault(Request $request, IntegrationAccount $account)
    {
        if (! \App\Services\Payments\Routing\PaymentAccountResolver::usable($account)) {
            return back()->with('error', 'Only an enabled and verified account can be the default.');
        }
        app(\App\Settings\SettingsService::class)->update(['default_payment_account_id' => $account->id], $request->user(), $request->ip());

        return back()->with('success', "\"{$account->name}\" is now the default merchant account for new payments.");
    }
}
