<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Behind a reverse proxy, the request Laravel sees may not carry the
        // original public host/port, which breaks generated asset/route
        // URLs. Force them to always use the configured APP_URL instead of
        // deriving from the (possibly proxy-mangled) incoming request.
        if ($url = config('app.url')) {
            URL::forceRootUrl($url);
        }
    }
}
