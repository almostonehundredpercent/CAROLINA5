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
