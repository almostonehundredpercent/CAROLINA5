<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates HTTPS before requests reach Laravel. Trust its forwarded
        // protocol headers so redirects and secure session cookies stay on HTTPS.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'admin.permission' => \App\Http\Middleware\RequireAdminPermission::class,
        ]);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\PreventAuthenticatedPageCaching::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render streams stderr to its application-log view. Keep the public
        // error page generic, but retain the exception details for operators.
        $exceptions->report(function (\Throwable $exception): void {
            Log::error('Unhandled application exception.', [
                'exception' => $exception,
            ]);
        });
    })
    ->create();
