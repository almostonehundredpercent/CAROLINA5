<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PreventAuthenticatedPageCaching;
use App\Http\Middleware\RequireAdminPermission;
use App\Http\Middleware\RequireStaffMfa;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('bookings:expire-holds')->everyMinute()->withoutOverlapping(10);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates HTTPS before requests reach Laravel. Trust its forwarded
        // protocol headers so redirects and secure session cookies stay on HTTPS.
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: ['webhooks/paymongo']);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'admin.permission' => RequireAdminPermission::class,
            'staff.mfa' => RequireStaffMfa::class,
        ]);

        $middleware->appendToGroup('web', [
            PreventAuthenticatedPageCaching::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render streams stderr to its application-log view. Keep the public
        // error page generic, but retain the exception details for operators.
        $exceptions->report(function (Throwable $exception): void {
            Log::error('Unhandled application exception.', [
                'exception' => $exception,
            ]);
        });
    })
    ->create();
