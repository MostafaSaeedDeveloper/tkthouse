<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/account/dashboard';

    public function username(): string
    {
        return 'login';
    }

    protected function credentials(Request $request): array
    {
        $login = (string) $request->input('login');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $field => $login,
            'password' => (string) $request->input('password'),
        ];
    }

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    protected function authenticated(Request $request, $user): void
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        activity('auth')->performedOn($user)->causedBy($user)->log('User logged in');
    }

    protected function redirectTo(): string
    {
        if (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('admin')) {
            return '/admin/dashboard';
        }

        return '/account/dashboard';
    }
}
