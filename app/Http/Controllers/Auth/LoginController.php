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
        $loginInput = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        // تحويل الأرقام العربية إلى إنجليزية في كلمة المرور تحسبًا للوحة المفاتيح العربية
        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalizedPassword = str_replace($arabicDigits, $englishDigits, $password);

        $remember = (bool) $request->boolean('remember');

        // السماح بتسجيل الدخول إما بالبريد الإلكتروني أو باسم المستخدم (مثل "الدكتور")
        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $attemptSuccess = Auth::attempt([$field => $loginInput, 'password' => $password], $remember)
            || ($normalizedPassword !== $password && Auth::attempt([$field => $loginInput, 'password' => $normalizedPassword], $remember));

        // محاولة إضافية إذا أدخل المستخدم الاسم ووجد له حساب مسجل
        if (! $attemptSuccess && $field === 'name') {
            $userByName = \App\Models\User::where('name', $loginInput)->orWhere('name', 'like', "%{$loginInput}%")->first();
            if ($userByName) {
                $attemptSuccess = Auth::attempt(['email' => $userByName->email, 'password' => $password], $remember)
                    || ($normalizedPassword !== $password && Auth::attempt(['email' => $userByName->email, 'password' => $normalizedPassword], $remember));
            }
        }

        if (! $attemptSuccess) {
            return back()
                ->withInput([
                    'email' => $loginInput,
                ])
                ->withErrors([
                    'email' => 'بيانات الدخول غير صحيحة، تأكد من كتابة البريد الإلكتروني (admin@bets.com) أو الاسم (الدكتور) وكلمة المرور (admin123) بدقة.',
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
