<?php

namespace App\Http\Controllers;

use App\Mail\RegisterOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class CustomerAuthController extends Controller
{
    private const ADMIN_ROLES = [
        'admin',
        'event_manager',
        'ticket_manager',
        'support',
        'scanner',
        'super_admin',
        'super admin',
    ];

    private const REGISTER_OTP_SESSION_KEY = 'auth.register_otp';

    public function showLogin()
    {
        return view('front.auth.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ]);

        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $validated['login'], 'password' => $validated['password']], $request->boolean('remember'))) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Invalid credentials.',
                    'errors' => ['login' => ['Invalid credentials.']],
                ], 422);
            }

            return back()->withErrors(['login' => 'Invalid credentials.'])->onlyInput('login');
        }

        $request->session()->regenerate();

        return $this->redirectAfterAuth($request, $validated['redirect_to'] ?? null, 'Welcome back, you are now signed in.');
    }

    public function showRegister()
    {
        if (Auth::check() && ! $this->isAdminUser(Auth::user())) {
            return redirect()->route('front.account.dashboard');
        }

        return view('front.auth.register');
    }

    public function requestRegisterOtp(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ]);

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $request->session()->put(self::REGISTER_OTP_SESSION_KEY, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password_hash' => Hash::make($validated['password']),
            'redirect_to' => $validated['redirect_to'] ?? null,
            'referrer_id' => (int) $request->session()->get('affiliate.referrer_id'),
            'otp_hash' => Hash::make($otpCode),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        Mail::to($validated['email'])->send(new RegisterOtpMail($otpCode));

        return response()->json([
            'message' => 'OTP has been sent to your email. Enter it to complete registration.',
            'otp_required' => true,
        ]);
    }

    public function verifyRegisterOtp(Request $request)
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ]);

        $pending = $request->session()->get(self::REGISTER_OTP_SESSION_KEY);
        if (! is_array($pending) || empty($pending['email'])) {
            return response()->json([
                'message' => 'Registration session expired. Please register again.',
                'errors' => ['otp' => ['Registration session expired. Please register again.']],
            ], 422);
        }

        if ((int) ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::REGISTER_OTP_SESSION_KEY);

            return response()->json([
                'message' => 'OTP expired. Please request a new one.',
                'errors' => ['otp' => ['OTP expired. Please request a new one.']],
            ], 422);
        }

        if (! Hash::check($validated['otp'], (string) ($pending['otp_hash'] ?? ''))) {
            return response()->json([
                'message' => 'Invalid OTP code.',
                'errors' => ['otp' => ['Invalid OTP code.']],
            ], 422);
        }

        if (User::query()->where('email', $pending['email'])->exists()) {
            $request->session()->forget(self::REGISTER_OTP_SESSION_KEY);

            return response()->json([
                'message' => 'This email is already registered. Please sign in.',
                'errors' => ['email' => ['This email is already registered. Please sign in.']],
            ], 422);
        }

        $user = User::create([
            'name' => $pending['name'],
            'username' => $this->generateUsername($pending['email']),
            'email' => $pending['email'],
            'phone' => $pending['phone'],
            'password' => $pending['password_hash'],
            'referred_by_user_id' => ((int) ($pending['referrer_id'] ?? 0)) > 0 ? (int) $pending['referrer_id'] : null,
        ]);

        $request->session()->forget('affiliate.referrer_id');
        $request->session()->forget('affiliate.referrer_code');
        $request->session()->forget(self::REGISTER_OTP_SESSION_KEY);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectAfterAuth($request, $pending['redirect_to'] ?? ($validated['redirect_to'] ?? null), 'Account created successfully. Welcome to TKT House.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('front.home');
    }

    private function redirectAfterAuth(Request $request, ?string $redirectTo, ?string $successMessage = null)
    {
        $target = route('front.account.dashboard');

        if (is_string($redirectTo) && $redirectTo !== '') {
            if (str_starts_with($redirectTo, '/') && ! str_starts_with($redirectTo, '//')) {
                $target = $redirectTo;
            }

            if (str_starts_with($redirectTo, url('/'))) {
                $target = $redirectTo;
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $successMessage ?? 'Success.',
                'redirect_to' => $target,
            ]);
        }

        return redirect()->to($target)->with('success', $successMessage ?? 'Success.');
    }

    private function generateUsername(string $email): string
    {
        $base = str((string) strtok($email, '@'))->slug('_')->value() ?: 'customer';
        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $suffix++;
            $username = $base.'_'.$suffix;
        }

        return $username;
    }

    private function isAdminUser($user): bool
    {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::ADMIN_ROLES);
    }
}
