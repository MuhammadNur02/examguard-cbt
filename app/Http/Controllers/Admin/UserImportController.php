<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AccountImporter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserImportController extends Controller
{
    public function create(): View
    {
        return view('admin.users.import', ['maksBaris' => AccountImporter::MAKS_BARIS]);
    }

    /**
     * Hasil dirender langsung (bukan redirect) agar kata sandi yang dibuat
     * sistem tidak pernah tersimpan di sesi.
     */
    public function store(Request $request, AccountImporter $importer): View|RedirectResponse
    {
        $request->validate([
            'berkas' => ['required', 'file', 'max:1024', 'mimes:csv,txt', 'extensions:csv,txt'],
        ], [], ['berkas' => 'berkas CSV']);

        $hasil = $importer->validasi($request->file('berkas')->getRealPath());

        if ($hasil['galat'] !== []) {
            return back()->with('galat_impor', $hasil['galat']);
        }

        set_time_limit(120);

        try {
            $kredensial = $importer->simpan($hasil['baris']);
        } catch (UniqueConstraintViolationException) {
            return back()->with('galat_impor', ['Sebagian NIM/NIDN bentrok dengan akun yang baru saja ditambahkan. Tidak ada yang disimpan; coba unggah ulang.']);
        }

        AuditLog::catat('akun_diimpor', null, ['jumlah' => count($kredensial)]);

        return view('admin.users.import-result', ['kredensial' => $kredensial]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, AccountImporter::KOLOM, ',', '"', '');
            fputcsv($out, ['2301001', 'Nama Mahasiswa Contoh', 'mahasiswa', ''], ',', '"', '');
            fputcsv($out, ['0601010101', 'Nama Dosen Contoh', 'dosen', ''], ',', '"', '');
            fclose($out);
        }, 'templat-impor-akun.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
