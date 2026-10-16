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

    public function index(Request $request)
    {
        $accounts = IntegrationAccount::query()->orderBy('provider')->orderBy('name')->get()->groupBy('provider');
        $methods = \App\Models\PaymentMethod::query()->where('is_active', true)->orderBy('id')->get(['code', 'name', 'provider'])->groupBy('provider');

        return view('admin.settings.integrations', [
            'definitions' => Definitions::all(),
            'accounts' => $accounts,
            'methodsByProvider' => $methods,
            'canManage' => $request->user()->can('settings.integrations.manage'),
            'canTest' => $request->user()->can('settings.integrations.test'),
        ]);
    }

    public function store(Request $request, string $provider)
    {
        abort_unless(Definitions::get($provider), 404);
        $request->validate([
            'name' => ['required', 'string', 'max:120', \Illuminate\Validation\Rule::unique('integration_accounts', 'name')->where('provider', $provider)],
            'environment' => ['required', 'in:sandbox,production'],
        ]);

        $account = $this->manager->save(null, $provider, $request->all(), $request->user());

        return redirect()->route('admin.integrations.index')->with('success', "Account \"{$account->name}\" created. Run a connection test before enabling it.");
    }

    public function update(Request $request, IntegrationAccount $account)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120', \Illuminate\Validation\Rule::unique('integration_accounts', 'name')->where('provider', $account->provider)->ignore($account->id)],
            'environment' => ['required', 'in:sandbox,production'],
        ]);

        $this->manager->save($account, $account->provider, $request->all(), $request->user());

        return redirect()->route('admin.integrations.index')->with('success', 'Account saved. If credentials changed it was disabled and must be re-tested.');
    }

    public function test(Request $request, IntegrationAccount $account)
    {
        $result = $this->manager->test($account, $request->user());

        return redirect()->route('admin.integrations.index')->with($result['verified'] ? 'success' : ($result['ok'] ? 'success' : 'error'), $account->name.': '.$result['message']);
    }

    public function enable(Request $request, IntegrationAccount $account)
    {
        $this->manager->enable($account, $request->boolean('confirm_production'), $request->user());

        return redirect()->route('admin.integrations.index')->with('success', "\"{$account->name}\" is now enabled.");
    }

    public function disable(Request $request, IntegrationAccount $account)
    {
        $this->manager->disable($account, $request->user());

        return redirect()->route('admin.integrations.index')->with('success', "\"{$account->name}\" was disabled.");
    }

    public function attest(Request $request, IntegrationAccount $account)
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:300']]);
        $this->manager->attestSandbox($account, $data['note'], $request->user());

        return redirect()->route('admin.integrations.index')->with('success', 'Sandbox verification recorded for the current credentials.');
    }

    public function archive(Request $request, IntegrationAccount $account)
    {
        $this->manager->archive($account, $request->user());

        return redirect()->route('admin.integrations.index')->with('success', "\"{$account->name}\" archived. Its payment history stays verifiable.");
    }

    public function destroy(Request $request, IntegrationAccount $account)
    {
        abort_if($account->enabled, 422, 'Disable the account before deleting it.');
        if ($account->isReferenced()) {
            return back()->with('error', 'This account has payment history, orders, users or reservations attached and cannot be deleted. Archive it instead.');
        }
        activity('settings')->causedBy($request->user())->withProperties(['provider' => $account->provider, 'name' => $account->name])->log('Integration account deleted');
        $account->delete();
        $this->manager->flush();

        return redirect()->route('admin.integrations.index')->with('success', 'Account deleted.');
    }
}
