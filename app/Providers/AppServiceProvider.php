<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // Password imara: herufi 8+, herufi kubwa na ndogo, namba na alama (mf. Test@1234).
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // Seva yenye SSL: viungo vyote viwe https (FORCE_HTTPS=true kwenye .env).
        if (filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN)) {
            URL::forceScheme('https');
        }
    }
}
