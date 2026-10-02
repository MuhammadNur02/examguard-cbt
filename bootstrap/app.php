<?php

use App\Exceptions\UjianTidakTersedia;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSingleSession;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureSingleSession::class, SecurityHeaders::class]);
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->role->beranda()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Kegagalan alur ujian untuk layar ujian (JSON): pesan + status attempt terbaru.
        $exceptions->render(function (UjianTidakTersedia $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), ...$e->data], $e->status);
            }
        });
    })->create();
