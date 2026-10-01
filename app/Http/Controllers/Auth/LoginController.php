<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => __('Email atau kata sandi tidak valid.'),
            ]);
        }

        $request->session()->regenerate();

        $dashboard = route('dashboard');
        $intended = $request->session()->pull('url.intended');
        $applicationUrl = rtrim((string) config('app.url'), '/');

        // Session dari host/root XAMPP lama dapat menyimpan http://localhost/
        // sebagai intended URL. URL tersebut berada di luar public base path ERP
        // dan menyebabkan redirect ke aplikasi XAMPP lain setelah login.
        if (is_string($intended) && ($intended === $applicationUrl || Str::startsWith($intended, $applicationUrl.'/'))) {
            return redirect()->to($intended);
        }

        return redirect()->to($dashboard);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
