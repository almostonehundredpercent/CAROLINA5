<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private function normalizeEmail(Request $request): void
    {
        if ($request->filled('email')) {
            // A backslash before @ is commonly introduced when an address is
            // copied from formatted text (for example, "name\\@domain.com").
            // It is not part of the address, so remove it before validation.
            $email = str_replace('\\@', '@', trim((string) $request->input('email')));
            $request->merge(['email' => strtolower($email)]);
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

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            ActivityLog::create([
                'user_id' => $user?->id,
                'event' => 'login_failed',
                'description' => 'A sign-in attempt failed.',
            ]);

            return back()->withErrors([
                'email' => 'That email and password do not match an account.',
            ])->onlyInput('email');
        }

        $request->session()->forget(['staff_mfa_user_id', 'staff_mfa_stamp', 'staff_mfa_setup_user_id', 'staff_mfa_setup_at']);
        auth()->login($user);
        $request->session()->regenerate();

        if ($user->hasStaffAccess()) {
            ActivityLog::create([
                'user_id' => $user->id,
                'event' => 'staff_password_accepted',
                'description' => 'Staff password accepted; MFA is still required.',
            ]);

            return redirect()->route($user->mfa_confirmed_at ? 'admin.mfa.challenge' : 'admin.mfa.setup');
        }

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

        ActivityLog::create([
            'user_id' => $user->id,
            'event' => 'user_registered',
            'description' => 'A guest account was created.',
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
        if ($request->user()?->hasStaffAccess()) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'staff_logout',
                'description' => 'Staff session ended.',
            ]);
        }

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
            ActivityLog::create([
                'user_id' => $user->id,
                'event' => 'password_changed',
                'description' => 'Account password changed through the reset flow.',
            ]);
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password reset. You can now sign in.')
            : back()->withErrors(['email' => __($status)]);
    }
}
