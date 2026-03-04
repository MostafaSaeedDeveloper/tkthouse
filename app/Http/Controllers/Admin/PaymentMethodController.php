<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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


    public function fawaterakMethods(Request $request)
    {
        $apiKey = trim((string) $request->query('api_key', ''));
        if ($apiKey === '') {
            return response()->json(['methods' => []]);
        }

        $base = rtrim((string) config('services.fawaterak.api_url', 'https://app.fawaterk.com/api/v2'), '/');
        if (! str_contains(strtolower($base), '/api/v2')) {
            $base .= '/api/v2';
        }

        $response = Http::baseUrl($base)
            ->timeout(20)
            ->withToken($apiKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->get('getPaymentmethods');

        if (! $response->successful()) {
            return response()->json([
                'message' => (string) $response->body(),
                'methods' => [],
            ], $response->status() ?: 422);
        }

        $json = $response->json();
        $methods = data_get($json, 'data', []);
        if (! is_array($methods) || isset($methods['paymentId'])) {
            $methods = data_get($json, 'data.payment_data', data_get($json, 'payment_methods', $methods));
        }

        $methods = collect(is_array($methods) ? $methods : [])
            ->filter(fn ($m) => is_array($m) && data_get($m, 'paymentId'))
            ->map(function ($m) {
                $id = (string) data_get($m, 'paymentId');
                $name = trim((string) (
                    data_get($m, 'name_ar')
                    ?: data_get($m, 'name')
                    ?: data_get($m, 'name_en')
                    ?: data_get($m, 'title_ar')
                    ?: data_get($m, 'title')
                    ?: data_get($m, 'title_en')
                    ?: data_get($m, 'paymentName')
                    ?: data_get($m, 'method_name')
                    ?: ''
                ));

                $providerKey = trim((string) (
                    data_get($m, 'providerKey')
                    ?: data_get($m, 'provider_key')
                    ?: data_get($m, 'key')
                    ?: ''
                ));

                return [
                    'id' => $id,
                    'provider_key' => $providerKey,
                    'name' => $name !== '' ? $name : 'Payment Method #'.$id,
                ];
            })
            ->values()
            ->all();

        return response()->json(['methods' => $methods]);
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $this->deleteCheckoutIcon($paymentMethod->checkout_icon);

        $paymentMethod->delete();

        return back()->with('success', 'Payment method deleted successfully.');
    }

    private function validateRequest(Request $request, ?PaymentMethod $method = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'checkout_label' => ['nullable', 'string', 'max:120'],
            'checkout_description' => ['nullable', 'string', 'max:255'],
            'checkout_icon_file' => ['nullable', 'image', 'max:2048'],
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('payment_methods', 'code')->ignore($method?->id),
            ],
            'provider' => ['required', Rule::in(['manual', 'paymob', 'fawaterak'])],
            'is_active' => ['nullable', 'boolean'],
            'paymob_api_key' => ['nullable', 'string'],
            'paymob_iframe_id' => ['nullable', 'string', 'max:50'],
            'paymob_integration_id' => ['nullable', 'string', 'max:50'],
            'fawaterak_api_key' => ['nullable', 'string'],
            'fawaterak_provider_key' => ['nullable', 'string', 'max:120'],
        ]);

        $config = [];
        if ($validated['provider'] === 'paymob') {
            $config = [
                'api_key' => (string) ($validated['paymob_api_key'] ?? ''),
                'iframe_id' => (string) ($validated['paymob_iframe_id'] ?? ''),
                'integration_id' => (string) ($validated['paymob_integration_id'] ?? ''),
            ];
        } elseif ($validated['provider'] === 'fawaterak') {
            $config = [
                'api_key' => (string) ($validated['fawaterak_api_key'] ?? ''),
                'provider_key' => (string) ($validated['fawaterak_provider_key'] ?? ''),
            ];
        }

        $iconPath = $this->migrateLegacyCheckoutIconToPublic($method?->checkout_icon);
        if ($request->hasFile('checkout_icon_file')) {
            $this->deleteCheckoutIcon($iconPath);
            $iconPath = $this->storeCheckoutIcon($request->file('checkout_icon_file'));
        }

        return [
            'name' => $validated['name'],
            'checkout_label' => trim((string) ($validated['checkout_label'] ?? '')) ?: $validated['name'],
            'checkout_icon' => $iconPath,
            'checkout_description' => trim((string) ($validated['checkout_description'] ?? '')) ?: null,
            'code' => strtolower($validated['code']),
            'provider' => $validated['provider'],
            'is_active' => $request->boolean('is_active'),
            'config' => $config,
        ];
    }


    private function migrateLegacyCheckoutIconToPublic(?string $iconPath): ?string
    {
        if (! $iconPath || ! str_starts_with($iconPath, 'payment-method-icons/')) {
            return $iconPath;
        }

        if (! Storage::disk('public')->exists($iconPath)) {
            return $iconPath;
        }

        $sourcePath = Storage::disk('public')->path($iconPath);
        $directory = 'uploads/payment-method-icons';
        $targetDirectory = public_path($directory);
        File::ensureDirectoryExists($targetDirectory);

        $filename = basename($sourcePath);
        $targetPath = $targetDirectory.'/'.$filename;

        if (File::exists($targetPath)) {
            $filename = pathinfo($filename, PATHINFO_FILENAME).'-'.uniqid().'.'.pathinfo($filename, PATHINFO_EXTENSION);
            $targetPath = $targetDirectory.'/'.$filename;
        }

        File::copy($sourcePath, $targetPath);
        Storage::disk('public')->delete($iconPath);

        return $directory.'/'.$filename;
    }

    private function storeCheckoutIcon(UploadedFile $file): string
    {
        $directory = 'uploads/payment-method-icons';
        $targetDirectory = public_path($directory);
        File::ensureDirectoryExists($targetDirectory);

        $filename = $file->hashName();
        $file->move($targetDirectory, $filename);

        return $directory.'/'.$filename;
    }

    private function deleteCheckoutIcon(?string $iconPath): void
    {
        if (! $iconPath) {
            return;
        }

        if (str_starts_with($iconPath, 'payment-method-icons/')) {
            Storage::disk('public')->delete($iconPath);

            return;
        }

        if (str_starts_with($iconPath, 'uploads/payment-method-icons/')) {
            File::delete(public_path($iconPath));
        }
    }
}
