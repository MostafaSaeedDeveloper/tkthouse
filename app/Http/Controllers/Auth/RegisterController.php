<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected $redirectTo = '/account/dashboard';

    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'username' => $this->generateUsername($data['email']),
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }

    private function generateUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '_');
        $base = $base !== '' ? $base : 'customer';
        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $suffix++;
            $username = $base.'_'.$suffix;
        }

        return $username;
    }
}
