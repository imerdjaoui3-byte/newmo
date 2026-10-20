<?php

namespace App\Providers;

use Illuminate\Support\Number;
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
        // Prices in dollars, without cents when there are none: $12, $12.50.
        Number::macro('money', fn (float|int|string|null $amount): string => '$'.number_format((float) $amount, fmod((float) $amount, 1) == 0 ? 0 : 2));
    }
}
