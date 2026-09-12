<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminPermission
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        abort_unless($request->user() && AdminPermissions::allows($request->user(), $ability), 403, 'Your staff role cannot use this area.');

        return $next($request);
    }
}
