<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerAuthController extends Controller
{
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
            return back()->withErrors(['login' => 'Invalid credentials.'])->onlyInput('login');
        }

        $request->session()->regenerate();

        return $this->redirectAfterAuth($request, $validated['redirect_to'] ?? null);
    }

    public function showRegister()
    {
        return view('front.auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $this->generateUsername($validated['email']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectAfterAuth($request, $validated['redirect_to'] ?? null);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('front.home');
    }


    private function redirectAfterAuth(Request $request, ?string $redirectTo)
    {
        if (is_string($redirectTo) && $redirectTo !== '') {
            if (str_starts_with($redirectTo, '/') && ! str_starts_with($redirectTo, '//')) {
                return redirect()->to($redirectTo);
            }

            if (str_starts_with($redirectTo, url('/'))) {
                return redirect()->to($redirectTo);
            }
        }

        return redirect()->intended(route('front.account.dashboard'));
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
}
