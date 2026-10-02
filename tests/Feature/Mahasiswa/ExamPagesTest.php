<?php

namespace Tests\Feature\Mahasiswa;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-04.10, FR-07.1–FR-07.3: halaman daftar ujian, persetujuan integritas,
 * layar pengerjaan, dan riwayat nilai.
 */
class ExamPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mahasiswa = User::factory()->mahasiswa()->create();
    }

    private function ujian(array $atribut = []): Exam
    {
        $exam = Exam::factory()->create($atribut);
        Question::factory()->pg()->create(['exam_id' => $exam->id]);
        Question::factory()->esai()->create(['exam_id' => $exam->id, 'kunci_esai' => 'KUNCI-ESAI-RAHASIA']);

        return $exam;
    }

    private function attemptSelesai(Exam $exam, FinishReason $alasan = FinishReason::Manual): ExamAttempt
    {
        return $exam->attempts()->create([
            'user_id' => $this->mahasiswa->id, 'shuffle_seed' => 1, 'urutan_soal' => [], 'urutan_opsi' => [],
            'mulai' => now()->subMinutes(30), 'selesai' => now()->subMinutes(5),
            'status' => $alasan === FinishReason::Pelanggaran ? AttemptStatus::Terkunci : AttemptStatus::Selesai,
            'alasan_selesai' => $alasan,
        ]);
    }

    public function test_daftar_ujian_menampilkan_keadaan_dan_menyembunyikan_draf(): void
    {
        $this->ujian(['judul' => 'Ujian Akan Datang', 'mulai' => now()->addDay()]);
        $this->ujian(['judul' => 'Ujian Dibuka']);
        $this->ujian(['judul' => 'Ujian Ditutup', 'mulai' => now()->subDay()]);
        $this->attemptSelesai($this->ujian(['judul' => 'Ujian Selesai']));
        $this->ujian(['judul' => 'Ujian Draf'])->update(['status' => 'draft']);

        $this->actingAs($this->mahasiswa)->get('/mahasiswa')
            ->assertOk()
            ->assertSeeInOrder(['Ujian Akan Datang', 'Akan datang'])
            ->assertSeeInOrder(['Ujian Dibuka', 'Dibuka', 'Masuk'])
            ->assertSeeInOrder(['Ujian Selesai', 'Selesai', 'Lihat'])
            ->assertSeeInOrder(['Ujian Ditutup', 'Ditutup'])
            ->assertDontSee('Ujian Draf');
    }

    public function test_halaman_ujian_dibuka_menampilkan_persetujuan_integritas(): void
    {
        $exam = $this->ujian(['batas_pelanggaran' => 3]);

        $this->actingAs($this->mahasiswa)->get("/mahasiswa/ujian/{$exam->id}")
            ->assertOk()
            ->assertSee('Persetujuan Integritas')
            ->assertSee('name="setuju"', false)
            ->assertSee('Pelanggaran ke-4 mengunci ujian', false)
            ->assertSee('data-persetujuan-tombol', false)
            ->assertDontSee('KUNCI-ESAI-RAHASIA');
    }

    public function test_mulai_lewat_form_tanpa_persetujuan_ditolak(): void
    {
        $exam = $this->ujian();

        $this->actingAs($this->mahasiswa)->from("/mahasiswa/ujian/{$exam->id}")
            ->post("/mahasiswa/ujian/{$exam->id}/mulai")
            ->assertRedirect("/mahasiswa/ujian/{$exam->id}")
            ->assertSessionHasErrors('setuju');
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_mulai_lewat_form_menuju_layar_pengerjaan(): void
    {
        $exam = $this->ujian();

        $this->actingAs($this->mahasiswa)->post("/mahasiswa/ujian/{$exam->id}/mulai", ['setuju' => '1'])
            ->assertRedirect("/mahasiswa/ujian/{$exam->id}/kerjakan");

        $html = $this->get("/mahasiswa/ujian/{$exam->id}/kerjakan")
            ->assertOk()
            ->assertSee('konfigurasi-ujian', false)
            ->assertSee('Masuk Layar Penuh')
            ->getContent();

        // Layar pengerjaan tidak menyisipkan soal maupun kunci; soal diambil lewat API tanpa kunci.
        $this->assertStringNotContainsString('KUNCI-ESAI-RAHASIA', $html);
        $this->assertStringNotContainsString('is_correct', $html);
    }

    public function test_mulai_di_luar_jadwal_lewat_form_kembali_dengan_pesan(): void
    {
        $exam = $this->ujian(['mulai' => now()->addHour()]);

        $this->actingAs($this->mahasiswa)->post("/mahasiswa/ujian/{$exam->id}/mulai", ['setuju' => '1'])
            ->assertRedirect("/mahasiswa/ujian/{$exam->id}")
            ->assertSessionHas('error');
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_layar_pengerjaan_tanpa_attempt_dialihkan(): void
    {
        $exam = $this->ujian();

        $this->actingAs($this->mahasiswa)->get("/mahasiswa/ujian/{$exam->id}/kerjakan")
            ->assertRedirect("/mahasiswa/ujian/{$exam->id}");
    }

    public function test_layar_pengerjaan_attempt_selesai_dialihkan_dengan_pesan(): void
    {
        $exam = $this->ujian();
        $this->attemptSelesai($exam, FinishReason::Pelanggaran);

        $this->actingAs($this->mahasiswa)->get("/mahasiswa/ujian/{$exam->id}/kerjakan")
            ->assertRedirect("/mahasiswa/ujian/{$exam->id}")
            ->assertSessionHas('status', 'Ujian Anda dikunci karena batas pelanggaran terlampaui. Jawaban yang tersimpan sudah dikirim.');

        $this->get("/mahasiswa/ujian/{$exam->id}")
            ->assertOk()
            ->assertSee('Jawaban terkirim')
            ->assertDontSee('name="setuju"', false);
    }

    public function test_ujian_draf_404_untuk_halaman_mahasiswa(): void
    {
        $exam = $this->ujian(['status' => 'draft']);

        $this->actingAs($this->mahasiswa)->get("/mahasiswa/ujian/{$exam->id}")->assertNotFound();
        $this->get("/mahasiswa/ujian/{$exam->id}/kerjakan")->assertNotFound();
    }

    public function test_nilai_tidak_terlihat_sebelum_dipublikasikan(): void
    {
        $belum = $this->attemptSelesai($this->ujian(['judul' => 'Ujian Belum Terbit Nilai']));
        $belum->result()->create(['skor_pg' => 2, 'skor_maksimal' => 12, 'nilai_akhir' => 81.25, 'dipublikasikan_pada' => null]);

        $terjadwal = $this->attemptSelesai($this->ujian(['judul' => 'Ujian Terbit Besok']));
        $terjadwal->result()->create(['skor_pg' => 2, 'skor_maksimal' => 12, 'nilai_akhir' => 66.5, 'dipublikasikan_pada' => now()->addDay()]);

        $terbit = $this->attemptSelesai($this->ujian(['judul' => 'Ujian Sudah Terbit']));
        $terbit->result()->create(['skor_pg' => 2, 'skor_maksimal' => 12, 'nilai_akhir' => 92.5, 'dipublikasikan_pada' => now()->subHour()]);

        $this->actingAs($this->mahasiswa)->get('/mahasiswa/nilai')
            ->assertOk()
            ->assertSeeInOrder(['Ujian Sudah Terbit', '92,5'])
            ->assertSee('Belum dipublikasikan')
            ->assertDontSee('81,25')
            ->assertDontSee('66,5');
    }

    public function test_riwayat_nilai_hanya_milik_sendiri(): void
    {
        $lain = User::factory()->mahasiswa()->create();
        $exam = $this->ujian(['judul' => 'Ujian Mahasiswa Lain']);
        $exam->attempts()->create([
            'user_id' => $lain->id, 'shuffle_seed' => 1, 'urutan_soal' => [], 'urutan_opsi' => [],
            'mulai' => now(), 'selesai' => now(), 'status' => AttemptStatus::Selesai,
        ]);

        $this->actingAs($this->mahasiswa)->get('/mahasiswa/nilai')->assertOk()->assertDontSee('Ujian Mahasiswa Lain');
    }
}
