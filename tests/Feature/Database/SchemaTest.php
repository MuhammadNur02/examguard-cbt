<?php

namespace Tests\Feature\Database;

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Models\Exam;
use App\Models\Kelas;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    /** Tabel dan kolom penting sesuai PRD §11 (plus tabel pendukung). */
    private const SKEMA = [
        'users' => ['id', 'nim_nidn', 'nama', 'role', 'password', 'aktif', 'session_token'],
        'classes' => ['id', 'nama'],
        'class_students' => ['class_id', 'user_id'],
        'exams' => ['id', 'dosen_id', 'judul', 'mata_kuliah', 'mulai', 'durasi_menit', 'batas_pelanggaran', 'acak_soal', 'acak_opsi', 'pool_size', 'status'],
        'exam_access' => ['exam_id', 'kode_akses', 'ip_allowlist'],
        'exam_classes' => ['exam_id', 'class_id'],
        'questions' => ['id', 'exam_id', 'tipe', 'teks', 'bobot', 'kunci_esai', 'keywords'],
        'options' => ['id', 'question_id', 'label', 'teks', 'is_correct', 'posisi_tetap'],
        'exam_attempts' => ['id', 'exam_id', 'user_id', 'shuffle_seed', 'urutan_soal', 'urutan_opsi', 'mulai', 'selesai', 'status', 'ip', 'user_agent', 'waktu_tambahan', 'jumlah_pelanggaran', 'terakhir_aktif'],
        'student_answers' => ['attempt_id', 'question_id', 'option_id', 'teks_jawaban', 'disimpan_pada', 'similarity', 'skor_sistem', 'skor_final'],
        'exam_logs' => ['attempt_id', 'jenis', 'waktu', 'detail', 'dihitung', 'dimaafkan', 'dimaafkan_oleh', 'alasan'],
        'exam_results' => ['attempt_id', 'skor_pg', 'skor_esai_sistem', 'skor_esai_final', 'nilai_akhir', 'dipublikasikan_pada'],
        'similarity_flags' => ['question_id', 'attempt_a', 'attempt_b', 'skor'],
        'audit_logs' => ['user_id', 'aksi', 'subjek_tipe', 'subjek_id', 'detail', 'ip', 'created_at'],
    ];

    public function test_semua_tabel_dan_kolom_penting_tersedia(): void
    {
        foreach (self::SKEMA as $tabel => $kolom) {
            $this->assertTrue(Schema::hasTable($tabel), "Tabel {$tabel} tidak ada.");
            $this->assertTrue(Schema::hasColumns($tabel, $kolom), "Kolom {$tabel} tidak lengkap.");
        }
    }

    public function test_seeder_demo_menghasilkan_data_yang_valid(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, User::where('role', Role::Admin)->count());
        $this->assertSame(2, User::where('role', Role::Dosen)->count());
        $this->assertSame(6, User::where('role', Role::Mahasiswa)->count());
        $this->assertSame(6, Kelas::firstWhere('nama', 'PTI 2023 A')->mahasiswa()->count());

        $uts = Exam::firstWhere('judul', 'UTS Pemrograman Web');
        $this->assertTrue($uts->isPublished());
        $this->assertTrue($uts->dalamJadwal());
        $this->assertSame(1, $uts->kelas()->count());
        $this->assertSame(7, $uts->questions()->count());

        foreach (Question::with('options')->get() as $question) {
            if ($question->tipe === QuestionType::Pg) {
                $this->assertSame(1, $question->options->where('is_correct', true)->count(), "Soal {$question->id} harus punya tepat satu kunci.");
            } else {
                $this->assertNotEmpty($question->kunci_esai, "Esai {$question->id} harus punya kunci.");
            }
        }
    }

    public function test_seeder_demo_menolak_lingkungan_produksi(): void
    {
        $this->app['env'] = 'production';

        // Dipanggil langsung: perintah db:seed sendiri meminta konfirmasi di produksi.
        $this->expectException(RuntimeException::class);
        $this->app->make(DemoSeeder::class)->run();
    }

    public function test_kunci_jawaban_tersembunyi_saat_serialisasi(): void
    {
        $pg = Question::factory()->pg(kunci: 'C')->create();
        $esai = Question::factory()->esai()->create();

        $opsi = $pg->options()->get()->toArray();
        $this->assertCount(4, $opsi);
        foreach ($opsi as $baris) {
            $this->assertArrayNotHasKey('is_correct', $baris);
        }

        $this->assertArrayNotHasKey('kunci_esai', $esai->toArray());
        $this->assertArrayNotHasKey('keywords', $esai->toArray());
        $this->assertStringNotContainsString('kunci_esai', $esai->toJson());
        $this->assertTrue(Option::where('question_id', $pg->id)->where('label', 'C')->value('is_correct'));
    }

    public function test_password_disimpan_dalam_bentuk_hash(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-123']);

        $this->assertNotSame('rahasia-123', $user->getRawOriginal('password'));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('session_token', $user->toArray());
    }
}
