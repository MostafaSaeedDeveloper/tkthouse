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
            $check = app(\App\Orders\AssignmentReadiness::class)->check($request->user(), (string) ($input['order_assignment_mode'] ?? 'manual'), (int) ($input['order_default_assignee_id'] ?? 0) ?: null);
            if ($check['blockers']) {
                return back()->withInput()->withErrors(['order_assignment_enabled' => implode(' ', $check['blockers'])]);
            }
            if ($check['lockedOut']->isNotEmpty() && ! $request->boolean('acknowledge_lockout')) {
                return back()->withInput()->withErrors(['order_assignment_enabled' => $check['lockedOut']->count().' staff member(s) can open Orders today but have neither orders.view_all nor orders.view_assigned and would see nothing. Grant them access below, or tick the acknowledgement.']);
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
