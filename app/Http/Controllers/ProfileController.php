<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();
        if ($request->filled('email')) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $email = Str::lower(trim($data['email']));
        $emailChanged = Str::lower($user->email) !== $email;
        $user->name = trim($data['name']);

        if ($emailChanged) {
            $user->email = $email;
            $user->email_verified_at = null;
        }

        $user->save();

        if (! $emailChanged) {
            return redirect()->route('bookings.index')->with('profile_success', 'Your profile details have been saved.');
        }

        try {
            $user->sendEmailVerificationNotification();
            $message = 'Your profile was saved. Check your new email address for a verification link.';
        } catch (Throwable $exception) {
            report($exception);
            $message = 'Your profile was saved. Verify your new email address from the verification page.';
        }

        return redirect()->route('bookings.index')->with('profile_success', $message);
    }
}
