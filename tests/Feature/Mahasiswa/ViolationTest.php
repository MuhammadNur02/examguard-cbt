<?php

namespace Tests\Feature\Mahasiswa;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Enums\LogType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamLog;
use App\Models\Question;
use App\Models\User;
use App\Services\ViolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-04.1/FR-04.4–FR-04.6 dan K-1/K-7: pelanggaran dicatat dan dihitung di server,
 * kejadian ganda dihitung sekali, pelanggaran ke-(N+1) mengunci dan mengirim ujian.
 */
class ViolationTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exam = Exam::factory()->create(['batas_pelanggaran' => 3, 'mulai' => now()->subMinutes(5), 'durasi_menit' => 60]);
        Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'urutan' => 1]);
        Question::factory()->esai()->create(['exam_id' => $this->exam->id, 'urutan' => 2]);
        $this->mahasiswa = User::factory()->mahasiswa()->create();

        $this->actingAs($this->mahasiswa)
            ->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1'])
            ->assertCreated();
    }

    private function lapor(string $jenis = 'pindah_tab', string $pemicu = 'blur')
    {
        return $this->postJson("/mahasiswa/ujian/{$this->exam->id}/pelanggaran", [
            'jenis' => $jenis,
            'pemicu' => $pemicu,
            'waktu_klien' => now()->toIso8601String(),
        ]);
    }

    private function attempt(): ExamAttempt
    {
        return ExamAttempt::sole();
    }

    public function test_pelanggaran_tercatat_dengan_milidetik_dan_detail(): void
    {
        $respons = $this->lapor('pindah_tab', 'visibilitychange')->assertOk();

        $respons->assertJson(['dihitung' => true, 'dikunci' => false, 'attempt' => ['jumlah_pelanggaran' => 1, 'batas_pelanggaran' => 3, 'status' => 'berlangsung']]);
        $log = ExamLog::sole();
        $this->assertSame(LogType::PindahTab, $log->jenis);
        $this->assertTrue($log->dihitung);
        $this->assertSame('visibilitychange', $log->detail['pemicu']);
        $this->assertArrayHasKey('waktu_klien', $log->detail);
        $this->assertMatchesRegularExpression('/\.\d{3}$/', $log->getRawOriginal('waktu'), 'Waktu log harus berpresisi milidetik.');
        $this->assertSame(1, $this->attempt()->jumlah_pelanggaran);
    }

    public function test_kejadian_ganda_dalam_jendela_debounce_dihitung_sekali(): void
    {
        $this->freezeTime();
        $this->lapor('pindah_tab', 'blur')->assertJson(['dihitung' => true]);

        $this->travel(400)->milliseconds();
        $this->lapor('pindah_tab', 'visibilitychange')->assertJson(['dihitung' => false]);
        $this->travel(400)->milliseconds();
        $this->lapor('keluar_fullscreen', 'fullscreenchange')->assertJson(['dihitung' => false]);

        $this->assertSame(1, $this->attempt()->jumlah_pelanggaran);
        $this->assertSame(1, ExamLog::count());

        // Kejadian baru setelah jendela debounce dihitung lagi.
        $this->travel(config('examguard.debounce_pelanggaran_ms') + 1)->milliseconds();
        $this->lapor()->assertJson(['dihitung' => true, 'attempt' => ['jumlah_pelanggaran' => 2]]);
    }

    public function test_pindah_tab_dan_keluar_fullscreen_memakai_penghitung_yang_sama(): void
    {
        $this->lapor('pindah_tab');
        $this->travel(3)->seconds();
        $this->lapor('keluar_fullscreen', 'fullscreenchange');

        $this->assertSame(2, $this->attempt()->jumlah_pelanggaran);
        $this->assertSame(['pindah_tab', 'keluar_fullscreen'], ExamLog::orderBy('id')->pluck('jenis')->map->value->all());
    }

    public function test_pelanggaran_ke_n_plus_1_mengunci_dan_mengirim_ujian(): void
    {
        // Jawaban yang tersimpan sebelum terkunci harus ikut terkirim.
        $nomorPg = collect($this->getJson("/mahasiswa/ujian/{$this->exam->id}/soal")->json('soal'))->firstWhere('tipe', 'pg')['nomor'];
        $this->postJson("/mahasiswa/ujian/{$this->exam->id}/jawaban", ['jawaban' => [['nomor' => $nomorPg, 'opsi' => 0]]])->assertOk();

        for ($i = 1; $i <= 3; $i++) {
            $this->travel(3)->seconds();
            $this->lapor()->assertJson(['dihitung' => true, 'dikunci' => false, 'attempt' => ['jumlah_pelanggaran' => $i, 'status' => 'berlangsung']]);
        }

        $this->travel(3)->seconds();
        $this->lapor()->assertOk()->assertJson([
            'dihitung' => true,
            'dikunci' => true,
            'attempt' => ['jumlah_pelanggaran' => 4, 'status' => 'terkunci', 'alasan_selesai' => 'pelanggaran'],
        ]);

        $attempt = $this->attempt();
        $this->assertSame(AttemptStatus::Terkunci, $attempt->status);
        $this->assertSame(FinishReason::Pelanggaran, $attempt->alasan_selesai);
        $this->assertNotNull($attempt->selesai);
        $this->assertSame(1, $attempt->answers()->whereNotNull('option_id')->count());

        // Lembar ujian beku: simpan jawaban dan laporan berikutnya ditolak dengan status terbaru.
        $this->postJson("/mahasiswa/ujian/{$this->exam->id}/jawaban", ['jawaban' => [['nomor' => $nomorPg, 'opsi' => 1]]])
            ->assertStatus(409)
            ->assertJson(['attempt' => ['status' => 'terkunci']]);
        $this->travel(3)->seconds();
        $this->lapor()->assertStatus(409);
        $this->assertSame(4, ExamLog::count());
    }

    public function test_jenis_yang_hanya_boleh_dicatat_server_ditolak(): void
    {
        $this->lapor('perangkat_berganti')->assertUnprocessable();
        $this->lapor('apa_saja')->assertUnprocessable();
        $this->postJson("/mahasiswa/ujian/{$this->exam->id}/pelanggaran", ['jenis' => 'pindah_tab', 'pemicu' => 'curang'])->assertUnprocessable();

        $this->assertSame(0, ExamLog::count());
    }

    public function test_pelanggaran_yang_dimaafkan_tidak_dihitung(): void
    {
        $this->lapor();
        $this->travel(3)->seconds();
        $this->lapor();
        ExamLog::orderBy('id')->first()->update(['dimaafkan' => true]);

        $this->assertSame(1, app(ViolationService::class)->hitungUlang($this->attempt()));
    }

    public function test_mahasiswa_tanpa_attempt_tidak_bisa_melapor(): void
    {
        $lain = User::factory()->mahasiswa()->create();

        $this->actingAs($lain)->postJson("/mahasiswa/ujian/{$this->exam->id}/pelanggaran", ['jenis' => 'pindah_tab'])->assertNotFound();
        $this->assertSame(0, ExamLog::count());
    }

    public function test_waktu_proses_server_pencatatan_di_bawah_satu_detik(): void
    {
        $mulai = microtime(true);
        $this->lapor()->assertOk();
        $durasiMs = (microtime(true) - $mulai) * 1000;

        // Hanya mengukur pemrosesan server di lingkungan tes, belum termasuk jaringan.
        $this->assertLessThan(1000, $durasiMs);
    }
}
