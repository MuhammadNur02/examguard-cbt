<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSingleSession;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        // Single session (K-8): token baru membatalkan sesi lain milik akun ini.
        $user = $request->user();
        $menggantikanSesi = $user->session_token !== null;
        $token = Str::random(64);
        $user->forceFill(['session_token' => $token])->save();
        $request->session()->put(EnsureSingleSession::SESSION_KEY, $token);

        if ($menggantikanSesi) {
            AuditLog::catat('sesi_diganti', $user, [
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ], $user);
        }

        // Peran menentukan halaman tujuan (FR-01.1).
        return redirect()->route($user->role->beranda());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tokenSesi = $request->session()->get(EnsureSingleSession::SESSION_KEY);

        if ($user && is_string($tokenSesi) && hash_equals((string) $user->session_token, $tokenSesi)) {
            $user->forceFill(['session_token' => null])->save();
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah keluar.');
    }
}
