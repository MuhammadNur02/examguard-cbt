<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk semua halaman web.
 */
class SecurityHeaders
{
    /**
     * Hanya sumber dari origin sendiri: tidak ada skrip/gaya inline maupun CDN.
     * Blok <script type="application/json"> berisi data, tidak dieksekusi.
     */
    public const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
        ."font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; "
        ."form-action 'self'; frame-ancestors 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Halaman (termasuk layar ujian) tidak boleh disematkan di iframe situs lain.
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Halaman galat mode debug memakai skrip inline dan server Vite dev memakai
        // origin lain, jadi CSP hanya dipasang saat debug mati (produksi).
        if (! config('app.debug')) {
            $response->headers->set('Content-Security-Policy', self::CSP);
        }

        return $response;
    }
}
