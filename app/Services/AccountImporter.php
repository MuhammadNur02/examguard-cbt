<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Support\CsvReader;
use App\Support\PasswordGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Impor akun dari CSV (FR-01.3). Seluruh berkas divalidasi lebih dulu; bila ada
 * satu baris salah, tidak ada yang disimpan dan galat dilaporkan per nomor baris.
 */
class AccountImporter
{
    public const MAKS_BARIS = 500;

    public const KOLOM = ['nim_nidn', 'nama', 'peran', 'kata_sandi'];

    /** Peran yang boleh dibuat lewat impor (admin hanya lewat form). */
    private const PERAN_IMPOR = ['mahasiswa', 'dosen'];

    /**
     * @return array{baris: list<array{line: int, nim_nidn: string, nama: string, peran: Role, kata_sandi: string}>, galat: list<string>}
     */
    public function validasi(string $path): array
    {
        try {
            $csv = CsvReader::read($path);
        } catch (RuntimeException $e) {
            return ['baris' => [], 'galat' => [$e->getMessage()]];
        }

        $kurang = array_diff(['nim_nidn', 'nama'], $csv['header']);
        if ($kurang !== []) {
            return ['baris' => [], 'galat' => ['Kolom wajib tidak ditemukan: '.implode(', ', $kurang).'. Gunakan templat CSV.']];
        }
        if ($csv['rows'] === []) {
            return ['baris' => [], 'galat' => ['Berkas tidak berisi baris data.']];
        }
        if (count($csv['rows']) > self::MAKS_BARIS) {
            return ['baris' => [], 'galat' => ['Maksimal '.self::MAKS_BARIS.' baris per berkas; pecah berkas menjadi beberapa bagian.']];
        }

        $terdaftar = User::whereIn('nim_nidn', array_map(fn ($r) => $r['data']['nim_nidn'], $csv['rows']))
            ->pluck('nim_nidn')
            ->flip();

        $baris = [];
        $galat = [];
        $dilihat = [];

        foreach ($csv['rows'] as ['line' => $line, 'data' => $data]) {
            $nim = $data['nim_nidn'];
            $nama = $data['nama'];
            $peran = strtolower($data['peran'] ?? '') ?: Role::Mahasiswa->value;
            $sandi = $data['kata_sandi'] ?? '';
            $galatBaris = [];

            if ($nim === '') {
                $galatBaris[] = 'NIM/NIDN wajib diisi';
            } elseif (! preg_match('/^[A-Za-z0-9._-]{1,30}$/', $nim)) {
                $galatBaris[] = 'NIM/NIDN hanya boleh huruf, angka, titik, garis bawah, atau tanda hubung (maks. 30 karakter)';
            } elseif (isset($terdaftar[$nim])) {
                $galatBaris[] = "NIM/NIDN {$nim} sudah terdaftar";
            } elseif (isset($dilihat[$nim])) {
                $galatBaris[] = "NIM/NIDN {$nim} ganda dengan baris {$dilihat[$nim]}";
            }

            if ($nama === '') {
                $galatBaris[] = 'nama wajib diisi';
            } elseif (mb_strlen($nama) > 255) {
                $galatBaris[] = 'nama maksimal 255 karakter';
            }

            if (! in_array($peran, self::PERAN_IMPOR, true)) {
                $galatBaris[] = 'peran harus mahasiswa atau dosen';
            }

            if ($sandi !== '' && mb_strlen($sandi) < 8) {
                $galatBaris[] = 'kata sandi minimal 8 karakter';
            }

            if ($nim !== '' && ! isset($dilihat[$nim])) {
                $dilihat[$nim] = $line;
            }

            if ($galatBaris !== []) {
                $galat[] = "Baris {$line}: ".implode('; ', $galatBaris).'.';

                continue;
            }

            $baris[] = ['line' => $line, 'nim_nidn' => $nim, 'nama' => $nama, 'peran' => Role::from($peran), 'kata_sandi' => $sandi];
        }

        return ['baris' => $galat === [] ? $baris : [], 'galat' => $galat];
    }

    /**
     * Simpan baris valid dalam satu transaksi.
     *
     * @param  list<array{nim_nidn: string, nama: string, peran: Role, kata_sandi: string}>  $baris
     * @return list<array{nim_nidn: string, nama: string, peran: Role, kata_sandi: ?string}> kata_sandi terisi hanya bila dibuat sistem
     */
    public function simpan(array $baris): array
    {
        // Cost bcrypt 10 agar impor ratusan akun tidak melampaui batas waktu;
        // Laravel menaikkannya ke cost bawaan saat login pertama (rehash_on_login).
        $rounds = min(10, (int) config('hashing.bcrypt.rounds', 12));

        return DB::transaction(function () use ($baris, $rounds) {
            $kredensial = [];
            foreach ($baris as $b) {
                $dibuatSistem = $b['kata_sandi'] === '';
                $sandi = $dibuatSistem ? PasswordGenerator::make() : $b['kata_sandi'];

                User::create([
                    'nim_nidn' => $b['nim_nidn'],
                    'nama' => $b['nama'],
                    'role' => $b['peran'],
                    'password' => Hash::make($sandi, ['rounds' => $rounds]),
                ]);

                $kredensial[] = [
                    'nim_nidn' => $b['nim_nidn'],
                    'nama' => $b['nama'],
                    'peran' => $b['peran'],
                    'kata_sandi' => $dibuatSistem ? $sandi : null,
                ];
            }

            return $kredensial;
        });
    }
}
