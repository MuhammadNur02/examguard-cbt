<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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

        // Tampilan paginasi memakai token StyleGuide.
        Paginator::defaultView('pagination.default');

        // Batas wajar permintaan layar ujian per mahasiswa (autosave, heartbeat, pelanggaran).
        RateLimiter::for('ujian', fn (Request $request) => Limit::perMinute((int) config('examguard.batas_permintaan_per_menit'))
            ->by($request->user()?->id ?: $request->ip()));

        // Parameter ID pada rute selalu numerik.
        Route::pattern('exam', '[0-9]+');
        Route::pattern('question', '[0-9]+');
        Route::pattern('user', '[0-9]+');
        Route::pattern('answer', '[0-9]+');
        Route::pattern('attempt', '[0-9]+');
        Route::pattern('kelas', '[0-9]+');
        Route::pattern('log', '[0-9]+');
    }
}
