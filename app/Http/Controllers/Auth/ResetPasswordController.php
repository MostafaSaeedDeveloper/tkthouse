<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller
{
    use ResetsPasswords;

    protected function redirectTo(): string
    {
        return route('front.customer.login');
    }

    public function showResetForm(Request $request, $token = null)
    {
        return view('front.auth.passwords.reset', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }
}
