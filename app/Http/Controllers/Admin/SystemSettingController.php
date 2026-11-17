<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\Registry;
use App\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Settings Center: one page per registry group. Values are validated and audited by SettingsService. */
class SystemSettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function edit(Request $request)
    {
        return $this->show($request, 'general');
    }

    public function update(Request $request)
    {
        return $this->save($request, 'general');
    }

    public function show(Request $request, string $section)
    {
        $group = $this->group($request, $section);

        return view('admin.settings.center', [
            'section' => $section,
            'group' => $group,
            'definitions' => Registry::forGroup($section),
            'values' => $this->settings->all(),
            'sections' => $this->visibleSections($request),
            'platform' => $this->platformStatus(),
            'paymentAccounts' => $section === 'payments' ? \App\Models\IntegrationAccount::query()->whereIn('provider', ['paymob', 'fawaterak', 'fake'])->orderBy('provider')->orderBy('name')->get()
                ->map(fn ($a) => [
                    'account' => $a, 'usable' => \App\Services\Payments\Routing\PaymentAccountResolver::usable($a),
                    'users' => \App\Models\User::query()->where('payment_account_id', $a->id)->count(),
                    'attempts' => \App\Models\PaymentAttempt::query()->where('integration_account_id', $a->id)->count(),
                    'open' => \App\Models\PaymentAttempt::query()->where('integration_account_id', $a->id)->where('status', 'initiated')->count(),
                ]) : null,
            'orderTools' => $section === 'orders' ? [
                'lockedOut' => app(\App\Orders\AssignmentReadiness::class)->lockedOut(),
                'eligible' => app(\App\Orders\OrderAssignmentService::class)->eligibleUsers(),
            ] : null,
        ]);
    }

    public function save(Request $request, string $section)
    {
        abort_unless($request->user()->can('settings.update'), 403);
        $this->group($request, $section);

        $input = [];
        foreach (Registry::forGroup($section) as $key => $def) {
            if ($def['type'] === 'file') {
                continue;
            }
            if ($def['type'] === 'bool') {
                $input[$key] = $request->boolean($key);
            } elseif ($request->has($key)) {
                $input[$key] = $request->input($key);
            }
        }

        if ($section === 'orders' && ($input['order_assignment_enabled'] ?? false) && ! \App\Orders\OrderAccess::enabled()) {
            $check = app(\App\Orders\AssignmentReadiness::class)->check($request->user(), (string) ($input['order_assignment_mode'] ?? 'manual'), array_values(array_filter(array_map('intval', (array) ($input['order_default_assignee_ids'] ?? [])))));
            if ($check['blockers']) {
                return back()->withInput()->withErrors(['order_assignment_enabled' => implode(' ', $check['blockers'])]);
            }
            if ($check['lockedOut']->isNotEmpty() && ! $request->boolean('acknowledge_lockout')) {
                return back()->withInput()->withErrors(['order_assignment_enabled' => $check['lockedOut']->count().' staff member(s) can open Orders today but have neither orders.view_all nor orders.view_assigned and would see nothing. Grant them access below, or tick the acknowledgement.']);
            }
        }

        // Several default assignees must be able to receive orders and share the same payment methods (also when assignment is already on).
        if ($section === 'orders' && ($input['order_assignment_mode'] ?? null) === 'default_assignee' && \App\Orders\OrderAccess::enabled()) {
            $ids = array_values(array_filter(array_map('intval', (array) ($input['order_default_assignee_ids'] ?? []))));
            if ($ids !== [] && ($problem = app(\App\Orders\AssignmentReadiness::class)->defaultAssigneeProblem($ids))) {
                return back()->withInput()->withErrors(['order_default_assignee_ids' => $problem]);
            }
        }

        if ($section === 'payments') {
            if (isset($input['default_payment_account_id']) && $input['default_payment_account_id'] !== '' && $input['default_payment_account_id'] !== null) {
                $acc = \App\Models\IntegrationAccount::query()->find((int) $input['default_payment_account_id']);
                if (! \App\Services\Payments\Routing\PaymentAccountResolver::usable($acc)) {
                    return back()->withInput()->withErrors(['default_payment_account_id' => 'The default merchant account must be enabled, verified and allowed in this environment.']);
                }
            }
            abort_unless($request->user()->can('settings.payment-accounts.manage'), 403);
        }

        if ($section === 'wallet') {
            $error = $this->saveWalletSecrets($request);
            if ($error) {
                return back()->withInput()->withErrors(['wallet_certificate' => $error]);
            }
        }

        $request->validate(collect(Registry::forGroup($section))->filter(fn ($d) => $d['type'] === 'file')->map(fn ($d) => $d['rules'])->all());

        $this->settings->update($input, $request->user(), $request->ip());

        foreach (Registry::forGroup($section) as $key => $def) {
            if ($def['type'] !== 'file' || ! $request->hasFile($key)) {
                continue;
            }
            $old = $this->settings->get($key);
            if (is_string($old) && str_starts_with($old, 'settings/')) {
                Storage::disk('public')->delete($old);
            }
            $this->settings->set($key, $request->file($key)->store('settings', 'public'), $section, $request->user()->id);
            \Illuminate\Support\Facades\DB::table('setting_audits')->insert(['key' => $key, 'group' => $section, 'old_value' => null, 'new_value' => '[file replaced]', 'user_id' => $request->user()->id, 'ip' => $request->ip(), 'created_at' => now()]);
        }

        return redirect()->route($section === 'general' ? 'admin.settings.edit' : 'admin.settings.section', $section === 'general' ? [] : ['section' => $section])
            ->with('success', 'Settings saved successfully.');
    }

    /** Apple Wallet certificate (.p12), its password and Apple's WWDR certificate: validated, then stored encrypted. @return ?string an error message */
    private function saveWalletSecrets(Request $request): ?string
    {
        $request->validate([
            'wallet_certificate' => ['nullable', 'file', 'max:512'],
            'wallet_wwdr' => ['nullable', 'file', 'max:256'],
            'wallet_certificate_password' => ['nullable', 'string', 'max:200'],
            'wallet_ww_api_key' => ['nullable', 'string', 'regex:/^ww_live_[A-Fa-f0-9]{32}$/'],
            'wallet_remove' => ['nullable', 'boolean'],
        ]);

        $key = 'wallet_apple_secrets';
        if ($request->boolean('wallet_remove')) {
            \App\Models\SystemSetting::query()->where('key', $key)->delete();
            $this->settings->flush();
            activity('settings')->causedBy($request->user())->log('Apple Wallet credentials removed');

            return null;
        }

        $current = \App\Services\Wallet\WalletSettings::secrets();
        $p12 = $request->hasFile('wallet_certificate') ? file_get_contents($request->file('wallet_certificate')->getRealPath()) : ($current['p12'] ?? null);
        $password = $request->filled('wallet_certificate_password') ? (string) $request->input('wallet_certificate_password') : ($request->hasFile('wallet_certificate') ? '' : ($current['password'] ?? ''));
        $wwdr = $current['wwdr'] ?? null;
        if ($request->hasFile('wallet_wwdr')) {
            $raw = file_get_contents($request->file('wallet_wwdr')->getRealPath());
            // Apple ships the WWDR certificate as .cer (DER); accept PEM too.
            $wwdr = str_contains($raw, '-----BEGIN CERTIFICATE-----')
                ? $raw
                : "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($raw), 64, "\n")."-----END CERTIFICATE-----\n";
            if (! openssl_x509_read($wwdr)) {
                return 'The WWDR file is not a valid certificate.';
            }
        }

        $apiKey = $request->filled('wallet_ww_api_key') ? trim((string) $request->input('wallet_ww_api_key')) : ($current['ww_api_key'] ?? null);

        if ($p12 === null && $wwdr === null && $apiKey === null) {
            return null; // nothing uploaded yet
        }
        if ($p12 !== null) {
            $parsed = [];
            if (! openssl_pkcs12_read($p12, $parsed, $password) || empty($parsed['cert']) || empty($parsed['pkey'])) {
                return 'The Pass Type ID certificate (.p12) could not be opened. Check the file and its password.';
            }
        }

        \App\Support\SystemSettings::set($key, \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
            'p12' => $p12 === null ? null : base64_encode($p12), 'password' => $password, 'wwdr' => $wwdr, 'ww_api_key' => $apiKey,
        ])));
        activity('settings')->causedBy($request->user())->log('Apple Wallet certificate updated');

        return null;
    }

    /** One click: keep every current order viewer's access before turning assignment on. */
    public function grantViewAll(Request $request)
    {
        abort_unless(\App\Orders\OrderAccess::isSuper($request->user()) || $request->user()->can('orders.view_all'), 403);
        $n = app(\App\Orders\AssignmentReadiness::class)->grantViewAllToLockedOut($request->user());

        return back()->with('success', $n ? "orders.view_all granted to {$n} user(s)." : 'Nobody needed it.');
    }

    /** Sends one plain e-mail to the signed-in staff member's own address only — never to customers. */
    public function testNotification(Request $request)
    {
        $to = (string) $request->user()->email;
        try {
            \Illuminate\Support\Facades\Mail::raw('This is a test message from the TKT House Settings Center. No action is needed.', fn ($m) => $m->to($to)->subject('TKT House — test notification'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Test e-mail failed: '.mb_substr($e->getMessage(), 0, 200));
        }

        return back()->with('success', 'Test e-mail sent to '.$to.'.');
    }

    private function group(Request $request, string $section): array
    {
        $group = Registry::GROUPS[$section] ?? null;
        abort_unless($group && $request->user()->can($group['permission']), $group ? 403 : 404);

        return $group;
    }

    /** @return array<string, array<string, mixed>> */
    private function visibleSections(Request $request): array
    {
        return array_filter(Registry::GROUPS, fn ($g) => $request->user()->can($g['permission']));
    }

    /** Read-only facts that are deliberately NOT editable from the UI (they live in the environment). */
    private function platformStatus(): array
    {
        return [
            'Environment' => app()->environment(),
            'Timezone' => config('app.timezone'),
            'Locale' => config('app.locale'),
            'Live payment gateways' => \App\Support\PaymentEnvironment::liveGatewaysAllowed() ? 'allowed' : 'disabled (PAYMENTS_LIVE_GATEWAYS)',
            'Fawaterak in production' => config('payments.fawaterak.verified') ? 'verified' : 'locked until sandbox verification',
            'Paymob callback signature' => \App\Support\PaymentEnvironment::paymobSignatureRequired() ? 'required' : 'optional',
        ];
    }
}
