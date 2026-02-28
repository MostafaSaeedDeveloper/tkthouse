<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::query()->orderByDesc('is_active')->orderBy('name')->get();

        return view('admin/settings/payment-methods/index', compact('methods'));
    }

    public function create()
    {
        return view('admin/settings/payment-methods/form', ['method' => new PaymentMethod()]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        PaymentMethod::create($validated);

        return redirect()->route('admin.payment-methods.index')->with('success', 'Payment method created successfully.');
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        return view('admin/settings/payment-methods/form', ['method' => $paymentMethod]);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $validated = $this->validateRequest($request, $paymentMethod);

        $paymentMethod->update($validated);

        return redirect()->route('admin.payment-methods.index')->with('success', 'Payment method updated successfully.');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $paymentMethod->delete();

        return back()->with('success', 'Payment method deleted successfully.');
    }

    private function validateRequest(Request $request, ?PaymentMethod $method = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'checkout_label' => ['nullable', 'string', 'max:120'],
            'checkout_icon' => ['nullable', 'string', 'max:16'],
            'checkout_description' => ['nullable', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('payment_methods', 'code')->ignore($method?->id),
            ],
            'provider' => ['required', Rule::in(['manual', 'paymob'])],
            'is_active' => ['nullable', 'boolean'],
            'paymob_api_key' => ['nullable', 'string'],
            'paymob_iframe_id' => ['nullable', 'string', 'max:50'],
            'paymob_integration_id' => ['nullable', 'string', 'max:50'],
        ]);

        $config = [];
        if ($validated['provider'] === 'paymob') {
            $config = [
                'api_key' => (string) ($validated['paymob_api_key'] ?? ''),
                'iframe_id' => (string) ($validated['paymob_iframe_id'] ?? ''),
                'integration_id' => (string) ($validated['paymob_integration_id'] ?? ''),
            ];
        }

        return [
            'name' => $validated['name'],
            'checkout_label' => trim((string) ($validated['checkout_label'] ?? '')) ?: $validated['name'],
            'checkout_icon' => trim((string) ($validated['checkout_icon'] ?? '')) ?: '💰',
            'checkout_description' => trim((string) ($validated['checkout_description'] ?? '')) ?: null,
            'code' => strtolower($validated['code']),
            'provider' => $validated['provider'],
            'is_active' => $request->boolean('is_active'),
            'config' => $config,
        ];
    }
}
