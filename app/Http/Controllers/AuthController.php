<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use App\Support\AdminPermissions;

class AuthController extends Controller
{
    private function normalizeEmail(Request $request): void
    {
        if ($request->filled('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function showLoginForm()
    {
        return redirect()->route('login');
    }

    public function showRegister()
    {
        return view('register');
    }

    public function login(Request $request)
    {
        $this->normalizeEmail($request);
        $credentials = $request->validate([
            'email' => 'required|email:rfc|max:255',
            'password' => 'required|min:6',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ])->onlyInput('email');
        }

        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route(AdminPermissions::landingRoute($user));
    }

    public function register(Request $request)
    {
        $this->normalizeEmail($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc|max:255|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => false,
        ]);

        auth()->login($user);
        $request->session()->regenerate();

        try {
            event(new Registered($user));
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->route('verification.notice')->withErrors([
                'email' => 'Your account was created, but we could not schedule the verification email. Please use Send another verification link below. You do not need to register again.',
            ]);
        }

        return redirect()->route('verification.notice')->with('success', 'Your account is ready. Your verification email is queued for delivery. Please check your inbox and spam folder shortly.');
    }

    public function logout(Request $request)
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $this->normalizeEmail($request);
        $request->validate(['email' => 'required|email:rfc|max:255']);
        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $exception) {
            report($exception);
        }
        return back()->with('success', 'If that address belongs to an account, a password reset link has been sent.');
    }

    public function showResetPassword(string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => request('email')]);
    }

    public function resetPassword(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['token' => 'required', 'email' => 'required|email:rfc|max:255', 'password' => 'required|min:8|confirmed']);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password reset. You can now sign in.')
            : back()->withErrors(['email' => __($status)]);
    }
}
