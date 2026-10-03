<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\User;
use App\Support\CsvReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Manajemen kelas dan keanggotaan mahasiswa oleh admin (FR-02.6, K-5).
 */
class ClassController extends Controller
{
    public function index(): View
    {
        return view('admin.classes.index', [
            'daftar' => Kelas::withCount(['mahasiswa', 'exams'])->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('classes', 'nama')],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $kelas = Kelas::create($data);
        AuditLog::catat('kelas_dibuat', $kelas);

        return redirect()->route('admin.classes.show', $kelas)->with('status', "Kelas {$kelas->nama} dibuat.");
    }

    public function show(Kelas $kelas): View
    {
        return view('admin.classes.show', [
            'kelas' => $kelas->loadCount('exams'),
            'anggota' => $kelas->mahasiswa()->orderBy('nim_nidn')->get(),
        ]);
    }

    public function update(Request $request, Kelas $kelas): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('classes', 'nama')->ignore($kelas->id)],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $kelas->update($data);

        return back()->with('status', 'Kelas diperbarui.');
    }

    public function destroy(Kelas $kelas): RedirectResponse
    {
        if ($kelas->exams()->exists()) {
            return back()->with('error', 'Kelas masih ditetapkan pada ujian sehingga tidak dapat dihapus.');
        }

        $kelas->delete();
        AuditLog::catat('kelas_dihapus', null, ['nama' => $kelas->nama]);

        return redirect()->route('admin.classes.index')->with('status', "Kelas {$kelas->nama} dihapus.");
    }

    public function addMember(Request $request, Kelas $kelas): RedirectResponse
    {
        $nim = trim((string) $request->validate(['nim_nidn' => ['required', 'string', 'max:30']])['nim_nidn']);
        $mahasiswa = User::where('nim_nidn', $nim)->where('role', Role::Mahasiswa)->first();

        if (! $mahasiswa) {
            return back()->withErrors(['nim_nidn' => "NIM {$nim} tidak terdaftar sebagai mahasiswa."])->withInput();
        }

        $kelas->mahasiswa()->syncWithoutDetaching([$mahasiswa->id]);

        return back()->with('status', "{$mahasiswa->nama} ({$nim}) masuk kelas {$kelas->nama}.");
    }

    public function removeMember(Kelas $kelas, User $user): RedirectResponse
    {
        $kelas->mahasiswa()->detach($user->id);

        return back()->with('status', "{$user->nama} dikeluarkan dari kelas {$kelas->nama}.");
    }

    /** Impor anggota dari CSV berkolom nim_nidn; satu baris salah membatalkan semuanya. */
    public function importMembers(Request $request, Kelas $kelas): RedirectResponse
    {
        $request->validate(['berkas' => ['required', 'file', 'max:1024', 'mimes:csv,txt', 'extensions:csv,txt']], [], ['berkas' => 'berkas CSV']);

        try {
            $csv = CsvReader::read($request->file('berkas')->getRealPath());
        } catch (RuntimeException $e) {
            return back()->with('galat_impor', [$e->getMessage()]);
        }
        if (! in_array('nim_nidn', $csv['header'], true)) {
            return back()->with('galat_impor', ['Kolom wajib tidak ditemukan: nim_nidn.']);
        }

        $nimDiBerkas = array_map(fn ($r) => $r['data']['nim_nidn'], $csv['rows']);
        $mahasiswa = User::whereIn('nim_nidn', $nimDiBerkas)->where('role', Role::Mahasiswa)->pluck('id', 'nim_nidn');

        $galat = [];
        foreach ($csv['rows'] as ['line' => $line, 'data' => $data]) {
            if ($data['nim_nidn'] === '') {
                $galat[] = "Baris {$line}: NIM wajib diisi.";
            } elseif (! isset($mahasiswa[$data['nim_nidn']])) {
                $galat[] = "Baris {$line}: NIM {$data['nim_nidn']} tidak terdaftar sebagai mahasiswa.";
            }
        }
        if ($galat !== [] || $mahasiswa->isEmpty()) {
            return back()->with('galat_impor', $galat ?: ['Berkas tidak berisi baris data.']);
        }

        $hasil = $kelas->mahasiswa()->syncWithoutDetaching($mahasiswa->values()->all());
        AuditLog::catat('anggota_kelas_diimpor', $kelas, ['ditambahkan' => count($hasil['attached'])]);

        return back()->with('status', count($hasil['attached']).' mahasiswa ditambahkan ke kelas '.$kelas->nama.'.');
    }
}
