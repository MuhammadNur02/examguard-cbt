<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        // Tangkap lazy loading (N+1) dan atribut yang dibuang diam-diam selama pengembangan.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
