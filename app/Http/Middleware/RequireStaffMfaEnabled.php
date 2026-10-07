<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffMfaEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('auth.staff_mfa_enabled') === false && $request->user()?->hasStaffAccess()) {
            return redirect()->route(AdminPermissions::landingRoute($request->user()));
        }

        return $next($request);
    }
}
