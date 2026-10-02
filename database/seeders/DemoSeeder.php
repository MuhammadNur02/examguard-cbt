<?php

namespace Database\Seeders;

use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Models\Exam;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data contoh untuk pengembangan dan demo. Tidak boleh dijalankan di produksi.
 * Kata sandi semua akun: nilai SEED_PASSWORD di .env (bawaan "password").
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di produksi.');
        }

        $password = (string) env('SEED_PASSWORD', 'password');

        User::create(['nim_nidn' => 'admin', 'nama' => 'Administrator Prodi', 'role' => Role::Admin, 'password' => $password]);

        $dosen = User::create(['nim_nidn' => '0601018801', 'nama' => 'Dr. Siti Rahmawati, M.Kom.', 'role' => Role::Dosen, 'password' => $password]);
        User::create(['nim_nidn' => '0612038502', 'nama' => 'Budi Santoso, M.Pd.', 'role' => Role::Dosen, 'password' => $password]);

        $namaMahasiswa = ['Andi Pratama', 'Bunga Lestari', 'Citra Dewi', 'Dimas Saputra', 'Eka Wulandari', 'Fajar Nugroho'];
        $mahasiswa = collect($namaMahasiswa)->map(fn (string $nama, int $i) => User::create([
            'nim_nidn' => sprintf('2301%03d', $i + 1),
            'nama' => $nama,
            'role' => Role::Mahasiswa,
            'password' => $password,
        ]));

        $kelas = Kelas::create(['nama' => 'PTI 2023 A', 'keterangan' => 'Pendidikan Informatika angkatan 2023']);
        $kelas->mahasiswa()->attach($mahasiswa->pluck('id'));

        $uts = $dosen->exams()->create([
            'judul' => 'UTS Pemrograman Web',
            'mata_kuliah' => 'Pemrograman Web',
            'mulai' => now()->subMinutes(5)->startOfMinute(),
            'durasi_menit' => 120,
            'batas_pelanggaran' => 3,
            'acak_soal' => true,
            'acak_opsi' => true,
            'status' => ExamStatus::Published,
        ]);
        $uts->kelas()->attach($kelas->id);
        $this->isiSoalUts($uts);

        $kuis = $dosen->exams()->create([
            'judul' => 'Kuis Basis Data',
            'mata_kuliah' => 'Basis Data',
            'mulai' => now()->addDays(7)->setTime(8, 0),
            'durasi_menit' => 30,
            'batas_pelanggaran' => 3,
            'acak_soal' => true,
            'acak_opsi' => true,
            'status' => ExamStatus::Draft,
        ]);
        $this->tambahPg($kuis, 1, 'Perintah SQL untuk mengambil data dari tabel adalah ...', [
            'A' => 'INSERT', 'B' => 'SELECT', 'C' => 'UPDATE', 'D' => 'DELETE',
        ], 'B');
    }

    private function isiSoalUts(Exam $exam): void
    {
        $this->tambahPg($exam, 1, 'Tag HTML yang digunakan untuk membuat tautan adalah ...', [
            'A' => '<link>', 'B' => '<a>', 'C' => '<href>', 'D' => '<url>', 'E' => '<nav>',
        ], 'B');
        $this->tambahPg($exam, 2, 'Metode HTTP yang lazim dipakai untuk mengirim formulir yang mengubah data di server adalah ...', [
            'A' => 'GET', 'B' => 'POST', 'C' => 'HEAD', 'D' => 'OPTIONS', 'E' => 'TRACE',
        ], 'B');
        $this->tambahPg($exam, 3, 'Properti CSS untuk mengatur warna teks adalah ...', [
            'A' => 'font-color', 'B' => 'text-color', 'C' => 'color', 'D' => 'foreground', 'E' => 'Semua jawaban salah',
        ], 'C', tetap: ['E']);
        $this->tambahPg($exam, 4, 'Pada Laravel, berkas yang memuat definisi rute web adalah ...', [
            'A' => 'routes/web.php', 'B' => 'app/web.php', 'C' => 'config/routes.php', 'D' => 'public/index.php', 'E' => 'bootstrap/app.js',
        ], 'A');
        $this->tambahPg($exam, 5, 'Kode status HTTP 404 berarti ...', [
            'A' => 'Kesalahan server', 'B' => 'Akses ditolak', 'C' => 'Sumber tidak ditemukan', 'D' => 'Permintaan berhasil', 'E' => 'Dialihkan',
        ], 'C');

        $exam->questions()->create([
            'urutan' => 6,
            'tipe' => QuestionType::Esai,
            'teks' => 'Jelaskan perbedaan metode GET dan POST pada HTTP.',
            'bobot' => 10,
            'kunci_esai' => 'Metode GET mengirim data melalui URL sehingga data terlihat dan panjangnya terbatas, cocok untuk mengambil data. Metode POST mengirim data di dalam badan permintaan sehingga tidak terlihat di URL dan cocok untuk mengirim atau mengubah data di server.',
            'keywords' => ['URL', 'badan permintaan', 'mengambil data', 'mengubah data'],
        ]);
        $exam->questions()->create([
            'urutan' => 7,
            'tipe' => QuestionType::Esai,
            'teks' => 'Jelaskan fungsi middleware pada Laravel.',
            'bobot' => 10,
            'kunci_esai' => 'Middleware berfungsi sebagai penyaring permintaan HTTP yang masuk ke aplikasi sebelum diteruskan ke controller, misalnya untuk memeriksa autentikasi pengguna, hak akses, atau token CSRF.',
            'keywords' => ['penyaring', 'permintaan', 'autentikasi', 'controller'],
        ]);
    }

    /**
     * @param  array<string, string>  $opsi  label => teks
     * @param  list<string>  $tetap  label opsi berposisi tetap
     */
    private function tambahPg(Exam $exam, int $urutan, string $teks, array $opsi, string $kunci, array $tetap = []): void
    {
        $question = $exam->questions()->create([
            'urutan' => $urutan,
            'tipe' => QuestionType::Pg,
            'teks' => $teks,
            'bobot' => 2,
        ]);

        foreach ($opsi as $label => $teksOpsi) {
            $question->options()->create([
                'label' => $label,
                'teks' => $teksOpsi,
                'is_correct' => $label === $kunci,
                'posisi_tetap' => in_array($label, $tetap, true),
            ]);
        }
    }
}
