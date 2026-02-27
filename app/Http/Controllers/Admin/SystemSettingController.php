<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemSettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', [
            'settings' => SystemSettings::all(),
            'paymentMethods' => SystemSettings::paymentMethods(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'primary_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'secondary_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['in:visa,wallet,paymob'],
            'paymob_enabled' => ['nullable', 'boolean'],
            'paymob_api_key' => ['nullable', 'string'],
            'paymob_iframe_id' => ['nullable', 'string', 'max:50'],
            'paymob_integration_id_card' => ['nullable', 'string', 'max:50'],
            'paymob_integration_id_wallet' => ['nullable', 'string', 'max:50'],
            'logo_light' => ['nullable', 'image', 'max:2048'],
            'logo_dark' => ['nullable', 'image', 'max:2048'],
            'logo_footer' => ['nullable', 'image', 'max:2048'],
        ]);

        foreach (['site_name', 'primary_color', 'secondary_color', 'paymob_api_key', 'paymob_iframe_id', 'paymob_integration_id_card', 'paymob_integration_id_wallet'] as $key) {
            SystemSettings::set($key, $validated[$key] ?? null);
        }

        SystemSettings::set('payment_methods', $validated['payment_methods'] ?? []);
        SystemSettings::set('paymob_enabled', $request->boolean('paymob_enabled'));

        foreach (['logo_light' => 'site_logo_light', 'logo_dark' => 'site_logo_dark', 'logo_footer' => 'site_logo_footer'] as $fileKey => $settingKey) {
            if (! $request->hasFile($fileKey)) {
                continue;
            }

            $old = SystemSettings::get($settingKey);
            if (is_string($old) && str_starts_with($old, 'settings/')) {
                Storage::disk('public')->delete($old);
            }

            $path = $request->file($fileKey)->store('settings', 'public');
            SystemSettings::set($settingKey, $path);
        }

        return back()->with('success', 'Settings saved successfully.');
    }
}
