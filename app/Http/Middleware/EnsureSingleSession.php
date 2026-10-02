<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single session login (FR-01.2, K-8): hanya sesi dengan token terbaru yang
 * berlaku. Sesi lama atau akun yang dinonaktifkan langsung dikeluarkan.
 */
class EnsureSingleSession
{
    public const SESSION_KEY = 'session_token';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $tokenSesi = $request->session()->get(self::SESSION_KEY);
        $tokenValid = is_string($tokenSesi)
            && is_string($user->session_token)
            && hash_equals($user->session_token, $tokenSesi);

        if ($user->aktif && $tokenValid) {
            return $next($request);
        }

        $pesan = $user->aktif ? __('auth.session_replaced') : __('auth.inactive');

        if ($user->aktif) {
            AuditLog::catat('sesi_lama_ditolak', $user, [
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'path' => $request->path(),
            ], $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $pesan, 'kode' => 'sesi_berakhir'], 401);
        }

        return redirect()->route('login')->with('status', $pesan);
    }
}
