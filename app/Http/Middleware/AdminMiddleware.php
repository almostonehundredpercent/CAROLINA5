<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect('/login')->with('error', 'Please login first');
        }

        // Check if user is an admin
        abort_unless(auth()->user()->hasStaffAccess(), 403, 'You do not have staff access to the admin panel.');

        return $next($request);
    }
}
