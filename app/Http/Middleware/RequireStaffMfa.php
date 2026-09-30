<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->mfa_secret || ! $user->mfa_confirmed_at) {
            return redirect()->route('admin.mfa.setup');
        }

        if ($request->session()->get('staff_mfa_user_id') !== $user->id
            || $request->session()->get('staff_mfa_stamp') !== $user->mfa_confirmed_at->getTimestamp()) {
            return redirect()->route('admin.mfa.challenge');
        }

        return $next($request);
    }
}
