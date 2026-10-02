<?php

namespace Tests\Feature\Mahasiswa;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-04.7, FR-07.1, FR-07.2: autosave, heartbeat, timer sisi server, kirim jawaban.
 */
class ExamSessionTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private User $mahasiswa;

    private int $nomorPg;

    private int $nomorEsai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        $this->exam = Exam::factory()->create(['mulai' => now()->subMinutes(10), 'durasi_menit' => 60]);
        Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'urutan' => 1]);
        Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'urutan' => 2]);
        Question::factory()->esai()->create(['exam_id' => $this->exam->id, 'urutan' => 3]);
        $this->mahasiswa = User::factory()->mahasiswa()->create();

        $this->actingAs($this->mahasiswa)->postJson($this->url('mulai'), ['setuju' => '1'])->assertCreated();
        $soal = $this->getJson($this->url('soal'))->json('soal');
        $this->nomorPg = collect($soal)->firstWhere('tipe', 'pg')['nomor'];
        $this->nomorEsai = collect($soal)->firstWhere('tipe', 'esai')['nomor'];
    }

    private function url(string $aksi): string
    {
        return "/mahasiswa/ujian/{$this->exam->id}/{$aksi}";
    }

    private function simpan(array $jawaban)
    {
        return $this->postJson($this->url('jawaban'), ['jawaban' => $jawaban]);
    }

    private function attempt(): ExamAttempt
    {
        return ExamAttempt::sole();
    }

    public function test_autosave_tersimpan_dan_muncul_kembali_saat_reload(): void
    {
        $this->simpan([
            ['nomor' => $this->nomorPg, 'opsi' => 2],
            ['nomor' => $this->nomorEsai, 'teks' => 'Middleware menyaring permintaan.', 'ragu' => true],
        ])->assertOk()->assertJsonStructure(['tersimpan_pada', 'attempt' => ['sisa_detik', 'status']]);

        $attempt = $this->attempt();
        $peta = app(AttemptService::class)->petakanPosisi($attempt, $this->nomorPg, 2);
        $this->assertSame($peta['option_id'], StudentAnswer::firstWhere('question_id', $peta['question_id'])->option_id);

        $soal = collect($this->getJson($this->url('soal'))->json('soal'))->keyBy('nomor');
        $this->assertSame(2, $soal[$this->nomorPg]['jawaban']);
        $this->assertSame('Middleware menyaring permintaan.', $soal[$this->nomorEsai]['jawaban']);
        $this->assertTrue($soal[$this->nomorEsai]['ragu']);
        $this->assertNotNull($attempt->terakhir_aktif);
    }

    public function test_jawaban_terbaru_menggantikan_yang_lama_dan_bisa_dikosongkan(): void
    {
        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => 0]]);
        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => 3]]);
        $this->assertSame(1, StudentAnswer::count());

        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => null]])->assertOk();
        $this->assertNull(StudentAnswer::sole()->option_id);

        // Hanya mengubah tanda ragu tidak menghapus pilihan.
        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => 1]]);
        $this->simpan([['nomor' => $this->nomorPg, 'ragu' => true]]);
        $this->assertNotNull(StudentAnswer::sole()->option_id);
        $this->assertTrue(StudentAnswer::sole()->ragu);
    }

    public function test_nomor_atau_opsi_di_luar_susunan_ditolak_tanpa_menyimpan_sebagian(): void
    {
        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => 1], ['nomor' => 99, 'opsi' => 0]])->assertUnprocessable();
        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => 9]])->assertUnprocessable();
        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => -1]])->assertUnprocessable();

        $this->assertSame(0, StudentAnswer::count());
    }

    public function test_payload_tidak_valid_ditolak(): void
    {
        $this->postJson($this->url('jawaban'), [])->assertUnprocessable();
        $this->simpan([['nomor' => $this->nomorEsai, 'teks' => str_repeat('a', 20001)]])->assertUnprocessable();
        $this->simpan(array_fill(0, 101, ['nomor' => $this->nomorPg, 'opsi' => 0]))->assertUnprocessable();
    }

    public function test_heartbeat_memperbarui_aktivitas_dan_sisa_waktu_dari_server(): void
    {
        $this->travel(30)->seconds();

        $respons = $this->postJson($this->url('heartbeat'))->assertOk();

        $this->assertSame(50 * 60 - 30, $respons->json('attempt.sisa_detik'));
        $this->assertTrue($this->attempt()->terakhir_aktif->equalTo(now()));
    }

    public function test_jawaban_dalam_toleransi_setelah_batas_waktu_diterima(): void
    {
        $this->travelTo($this->exam->selesaiPada()->addSeconds(5));

        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => 1]])->assertOk();
        $this->assertSame(AttemptStatus::Berlangsung, $this->attempt()->status);
    }

    public function test_lewat_toleransi_attempt_ditutup_waktu_habis_dan_jawaban_tersimpan_tetap_ada(): void
    {
        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => 1]]);
        $this->travelTo($this->exam->selesaiPada()->addSeconds(config('examguard.toleransi_simpan_detik') + 1));

        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => 2]])->assertStatus(409)
            ->assertJson(['attempt' => ['status' => 'selesai', 'alasan_selesai' => 'waktu_habis']]);

        $attempt = $this->attempt();
        $this->assertSame(FinishReason::WaktuHabis, $attempt->alasan_selesai);
        $peta = app(AttemptService::class)->petakanPosisi($attempt, $this->nomorPg, 1);
        $this->assertSame($peta['option_id'], StudentAnswer::sole()->option_id);
    }

    public function test_waktu_tambahan_memperpanjang_batas(): void
    {
        $this->attempt()->update(['waktu_tambahan' => 10]);
        $this->travelTo($this->exam->selesaiPada()->addMinutes(5));

        $this->postJson($this->url('heartbeat'))->assertOk()->assertJson(['attempt' => ['sisa_detik' => 300, 'status' => 'berlangsung']]);
    }

    public function test_kirim_memfinalkan_attempt_dan_idempoten(): void
    {
        $this->postJson($this->url('kirim'))->assertOk()->assertJson(['attempt' => ['status' => 'selesai', 'alasan_selesai' => 'manual']]);
        $selesai = $this->attempt()->selesai;

        $this->travel(5)->seconds();
        $this->postJson($this->url('kirim'))->assertOk()->assertJson(['attempt' => ['status' => 'selesai']]);
        $this->assertTrue($this->attempt()->selesai->equalTo($selesai));

        $this->simpan([['nomor' => $this->nomorPg, 'opsi' => 1]])->assertStatus(409);
        $this->getJson($this->url('soal'))->assertStatus(409)->assertJson(['message' => 'Ujian ini sudah Anda selesaikan.']);
    }

    public function test_kirim_lewat_form_mengalihkan_ke_halaman_ujian(): void
    {
        $this->post($this->url('kirim'))
            ->assertRedirect("/mahasiswa/ujian/{$this->exam->id}")
            ->assertSessionHas('status', 'Jawaban Anda telah dikirim.');
    }

    public function test_perintah_terjadwal_menutup_attempt_kedaluwarsa_saja(): void
    {
        $habis = $this->attempt();
        $ujianLain = Exam::factory()->create(['mulai' => now(), 'durasi_menit' => 600]);
        Question::factory()->pg()->create(['exam_id' => $ujianLain->id]);
        $this->actingAs(User::factory()->mahasiswa()->create())->postJson("/mahasiswa/ujian/{$ujianLain->id}/mulai", ['setuju' => '1']);
        $aktif = ExamAttempt::where('exam_id', $ujianLain->id)->sole();

        $this->travelTo($this->exam->selesaiPada()->addMinutes(2));
        $this->artisan('ujian:tutup-kedaluwarsa')->expectsOutput('1 attempt ditutup karena waktu habis.')->assertSuccessful();

        $this->assertSame(AttemptStatus::Selesai, $habis->fresh()->status);
        $this->assertSame(FinishReason::WaktuHabis, $habis->fresh()->alasan_selesai);
        $this->assertSame(AttemptStatus::Berlangsung, $aktif->fresh()->status);
    }

    public function test_endpoint_tidak_bisa_dipakai_tanpa_attempt_sendiri(): void
    {
        $this->actingAs(User::factory()->mahasiswa()->create());

        $this->simpan([['nomor' => 1, 'opsi' => 0]])->assertNotFound();
        $this->postJson($this->url('heartbeat'))->assertNotFound();
        $this->postJson($this->url('kirim'))->assertNotFound();
        $this->assertSame(AttemptStatus::Berlangsung, $this->attempt()->status);
    }
}
