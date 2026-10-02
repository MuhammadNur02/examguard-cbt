<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\PasswordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manajemen akun oleh admin (FR-01.3): daftar, tambah, reset kata sandi,
 * reset sesi, aktif/nonaktif. Akun tidak dihapus agar riwayat ujian utuh.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $peran = Role::tryFrom((string) $request->query('peran'));
        $status = in_array($request->query('status'), ['aktif', 'nonaktif'], true) ? $request->query('status') : null;
        $cari = trim((string) $request->query('q'));

        $users = User::query()
            ->when($peran, fn ($q) => $q->where('role', $peran))
            ->when($status, fn ($q) => $q->where('aktif', $status === 'aktif'))
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nim_nidn', 'like', '%'.$cari.'%')
                ->orWhere('nama', 'like', '%'.$cari.'%')))
            ->orderBy('role')
            ->orderBy('nim_nidn')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filter' => ['peran' => $peran?->value, 'status' => $status, 'q' => $cari],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nim_nidn' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'nim_nidn')],
            'nama' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ], [], ['role' => 'peran', 'password' => 'kata sandi']);

        $dibuatSistem = blank($data['password'] ?? null);
        $sandi = $dibuatSistem ? PasswordGenerator::make() : $data['password'];

        $user = User::create([
            'nim_nidn' => $data['nim_nidn'],
            'nama' => $data['nama'],
            'role' => $data['role'],
            'password' => $sandi,
        ]);
        AuditLog::catat('akun_dibuat', $user, ['peran' => $user->role->value]);

        $redirect = redirect()->route('admin.users.index')->with('status', "Akun {$user->nim_nidn} berhasil dibuat.");

        return $dibuatSistem ? $redirect->with('kredensial', $this->kredensial($user, $sandi)) : $redirect;
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $sandi = PasswordGenerator::make();
        // Token sesi dikosongkan sehingga sesi yang sedang aktif ikut berakhir.
        $user->forceFill(['password' => $sandi, 'session_token' => null])->save();
        AuditLog::catat('reset_password', $user);

        return back()
            ->with('status', "Kata sandi {$user->nim_nidn} diatur ulang.")
            ->with('kredensial', $this->kredensial($user, $sandi));
    }

    public function resetSession(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Untuk sesi Anda sendiri, gunakan tombol Keluar.');
        }

        $user->forceFill(['session_token' => null])->save();
        AuditLog::catat('reset_sesi', $user);

        return back()->with('status', "Sesi {$user->nim_nidn} diakhiri; pengguna harus login ulang.");
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $aktif = $request->validate(['aktif' => ['required', 'boolean']])['aktif'];
        $aktif = filter_var($aktif, FILTER_VALIDATE_BOOLEAN);

        if (! $aktif && $user->is($request->user())) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        if ($user->aktif !== $aktif) {
            $user->forceFill(['aktif' => $aktif])->save();
            AuditLog::catat($aktif ? 'akun_diaktifkan' : 'akun_dinonaktifkan', $user);
        }

        return back()->with('status', "Akun {$user->nim_nidn} ".($aktif ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    /** @return array{nim_nidn: string, nama: string, kata_sandi: string} */
    private function kredensial(User $user, string $sandi): array
    {
        return ['nim_nidn' => $user->nim_nidn, 'nama' => $user->nama, 'kata_sandi' => $sandi];
    }
}
