<?php

namespace Database\Seeders;

use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Data uji beban (Task 5.4, PRD §13.3): N mahasiswa B0001..BNNNN dan satu ujian
 * yang sedang dibuka (20 PG + 2 esai). Jalankan pada basis data terpisah, mis.
 * DB_DATABASE=database/beban.sqlite. Tidak boleh dijalankan di produksi.
 * Kata sandi: LOAD_TEST_PASSWORD (bawaan "password"); jumlah: LOAD_TEST_USERS (bawaan 100).
 */
class LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LoadTestSeeder tidak boleh dijalankan di produksi.');
        }

        $jumlah = max(1, (int) env('LOAD_TEST_USERS', 100));
        // Satu hash dipakai semua akun agar penyiapan tidak menghabiskan waktu untuk bcrypt.
        $hash = Hash::make((string) env('LOAD_TEST_PASSWORD', 'password'));

        $dosen = User::create(['nim_nidn' => 'beban-dosen', 'nama' => 'Dosen Uji Beban', 'role' => Role::Dosen, 'password' => $hash]);
        for ($i = 1; $i <= $jumlah; $i++) {
            User::create(['nim_nidn' => sprintf('B%04d', $i), 'nama' => "Peserta Beban {$i}", 'role' => Role::Mahasiswa, 'password' => $hash]);
        }

        $exam = $dosen->exams()->create([
            'judul' => 'Uji Beban',
            'mata_kuliah' => 'Pengujian',
            'mulai' => now()->subMinute()->startOfMinute(),
            'durasi_menit' => 180,
            'batas_pelanggaran' => 3,
            'acak_soal' => true,
            'acak_opsi' => true,
            'status' => ExamStatus::Published,
        ]);

        for ($n = 1; $n <= 20; $n++) {
            $soal = $exam->questions()->create(['urutan' => $n, 'tipe' => QuestionType::Pg, 'teks' => "Soal pilihan ganda nomor {$n}?", 'bobot' => 1]);
            foreach (['A', 'B', 'C', 'D'] as $label) {
                $soal->options()->create(['label' => $label, 'teks' => "Opsi {$label} soal {$n}", 'is_correct' => $label === 'B', 'posisi_tetap' => false]);
            }
        }
        foreach ([21, 22] as $n) {
            $exam->questions()->create([
                'urutan' => $n, 'tipe' => QuestionType::Esai, 'teks' => "Soal esai nomor {$n}.", 'bobot' => 10,
                'kunci_esai' => 'Middleware menyaring permintaan HTTP sebelum diteruskan ke controller.',
            ]);
        }

        $this->command?->info("Ujian uji beban #{$exam->id} dengan {$jumlah} peserta (B0001..".sprintf('B%04d', $jumlah).').');
    }
}
