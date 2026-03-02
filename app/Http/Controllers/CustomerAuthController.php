<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use App\Mail\CustomerEmailOtpMail;

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

        if (! $request->user()->email_verified_at) {
            $user = $request->user();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->issueEmailOtp($user);
            $request->session()->put('auth.pending_email_verification_user_id', $user->id);

            return redirect()->route('front.customer.verify-otp.show')
                ->withErrors(['otp' => 'Please verify your email first. We sent a fresh OTP code.']);
        }

        return $this->redirectAfterAuth($request, $validated['redirect_to'] ?? null, 'Welcome back, you are now signed in.');
    }

    public function showRegister()
    {
        if (Auth::check() && ! $this->isAdminUser(Auth::user())) {
            return redirect()->route('front.account.dashboard');
        }

        return view('front.auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ]);

        $referrerId = (int) $request->session()->get('affiliate.referrer_id');

        $user = User::create([
            'name' => $validated['name'],
            'username' => $this->generateUsername($validated['email']),
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'referred_by_user_id' => $referrerId > 0 ? $referrerId : null,
        ]);

        $request->session()->forget('affiliate.referrer_id');
        $request->session()->forget('affiliate.referrer_code');

        $this->issueEmailOtp($user);
        $request->session()->put('auth.pending_email_verification_user_id', $user->id);

        $currentUser = Auth::user();
        if (! $this->isAdminUser($currentUser)) {
            return redirect()->route('front.customer.verify-otp.show')
                ->with('success', 'Account created successfully. We sent a verification OTP to your email.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Customer account created successfully. OTP sent for email verification.',
                'created_user_id' => $user->id,
            ], 201);
        }

        return back()->with('success', 'Customer account created successfully. OTP sent for email verification.');
    }

    public function showVerifyOtp(Request $request)
    {
        if (! $request->session()->has('auth.pending_email_verification_user_id')) {
            return redirect()->route('front.customer.login');
        }

        return view('front.auth.verify-otp');
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ]);

        $userId = (int) $request->session()->get('auth.pending_email_verification_user_id');
        $user = User::query()->find($userId);

        if (! $user) {
            return redirect()->route('front.customer.register')->withErrors(['otp' => 'Verification session expired. Please register again.']);
        }

        if (! $user->email_otp_code || ! $user->email_otp_expires_at || now()->gt($user->email_otp_expires_at)) {
            return back()->withErrors(['otp' => 'OTP expired. Please request a new code.']);
        }

        if (! Hash::check($validated['otp'], $user->email_otp_code)) {
            return back()->withErrors(['otp' => 'Invalid OTP code.']);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_otp_code' => null,
            'email_otp_expires_at' => null,
        ])->save();

        $request->session()->forget('auth.pending_email_verification_user_id');
        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectAfterAuth($request, $validated['redirect_to'] ?? null, 'Email verified successfully. Welcome to TKT House.');
    }

    public function resendOtp(Request $request)
    {
        $userId = (int) $request->session()->get('auth.pending_email_verification_user_id');
        $user = User::query()->find($userId);

        if (! $user) {
            return redirect()->route('front.customer.register')->withErrors(['otp' => 'Verification session expired. Please register again.']);
        }

        $this->issueEmailOtp($user);

        return back()->with('success', 'A new OTP code has been sent to your email.');
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

    private function issueEmailOtp(User $user): void
    {
        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'email_otp_code' => Hash::make($otp),
            'email_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        Mail::to($user->email)->send(new CustomerEmailOtpMail($otp));
    }
}
