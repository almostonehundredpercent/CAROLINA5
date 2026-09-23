<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

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
        \Illuminate\Support\Facades\Mail::extend('brevo', function () {
            $key = (string) config('services.brevo.key');
            if ($key === '') {
                throw new \RuntimeException('BREVO_API_KEY is missing. Configure it in the hosting environment.');
            }
            return new \Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport(
                $key,
                \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 10, 'max_duration' => 15]),
            );
        });
        // The application does not load Tailwind. Use Bootstrap-compatible
        // pagination markup so the shared admin stylesheet can style it.
        Paginator::useBootstrapFive();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            if (! $this->app->runningInConsole()) {
                URL::forceRootUrl('https://' . request()->getHost());
            }
        }
    }
}
