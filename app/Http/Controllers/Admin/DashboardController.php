<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $aktif = fn (Role $role) => User::where('role', $role)->where('aktif', true)->count();

        return view('admin.dashboard', [
            'jumlahMahasiswa' => $aktif(Role::Mahasiswa),
            'jumlahDosen' => $aktif(Role::Dosen),
            'jumlahAdmin' => $aktif(Role::Admin),
            'jumlahNonaktif' => User::where('aktif', false)->count(),
            'jumlahKelas' => Kelas::count(),
        ]);
    }
}
