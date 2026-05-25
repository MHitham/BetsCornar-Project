<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        $credentials = $validated;
        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);

        if (! Auth::attempt($credentials, $remember)) {
            return back()
                ->withInput([
                    'email' => $validated['email'] ?? null,
                ])
                ->withErrors([
                    'email' => 'بيانات الدخول غير صحيحة',
                ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
