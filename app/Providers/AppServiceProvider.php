<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('staff-login', function (Request $request) {
            $identifier = strtolower(str_replace('\\@', '@', trim((string) $request->input('email'))));
            $account = hash('sha256', $identifier);

            return [
                Limit::perMinute(5)->by('login-account:'.$account.'|'.$request->ip()),
                Limit::perMinute(10)->by('login-identifier:'.$account),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        Mail::extend('brevo', function () {
            $key = (string) config('services.brevo.key');
            if ($key === '') {
                throw new \RuntimeException('BREVO_API_KEY is missing. Configure it in the hosting environment.');
            }

            return new BrevoApiTransport(
                $key,
                HttpClient::create(['timeout' => 10, 'max_duration' => 15]),
            );
        });
        // The application does not load Tailwind. Use Bootstrap-compatible
        // pagination markup so the shared admin stylesheet can style it.
        Paginator::useBootstrapFive();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            if (! $this->app->runningInConsole()) {
                URL::forceRootUrl('https://'.request()->getHost());
            }
        }
    }
}
