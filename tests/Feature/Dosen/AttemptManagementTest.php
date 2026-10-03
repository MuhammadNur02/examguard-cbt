<?php

namespace Tests\Feature\Dosen;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Enums\LogType;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamLog;
use App\Models\Question;
use App\Models\User;
use App\Services\ViolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 4.8: kelola pelanggaran (FR-06.5) serta tambah waktu dan buka ulang
 * attempt (FR-06.6). Setiap tindakan wajib beralasan dan tercatat di audit.
 */
class AttemptManagementTest extends TestCase
{
    use RefreshDatabase;

    private const ALASAN = 'Muat ulang tidak sengaja, dikonfirmasi pengawas ruang.';

    private User $dosen;

    private User $mahasiswa;

    private Exam $exam;

    private Question $esai;

    private ExamAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
        $this->dosen = User::factory()->dosen()->create(['nama' => 'Dosen Pengampu']);
        $this->mahasiswa = User::factory()->mahasiswa()->create();
        $this->exam = Exam::factory()->create([
            'dosen_id' => $this->dosen->id, 'mulai' => now()->subMinutes(10), 'durasi_menit' => 60, 'batas_pelanggaran' => 3,
        ]);
        Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'urutan' => 1]);
        $this->esai = Question::factory()->esai()->create(['exam_id' => $this->exam->id, 'urutan' => 2]);

        $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('mulai'), ['setuju' => '1'])->assertCreated();
        $this->attempt = ExamAttempt::sole();
    }

    private function urlMhs(string $akhiran): string
    {
        return "/mahasiswa/ujian/{$this->exam->id}/{$akhiran}";
    }

    private function urlDosen(string $akhiran): string
    {
        return "/dosen/ujian/{$this->exam->id}/peserta/{$this->attempt->id}/{$akhiran}";
    }

    private function pelanggaran(int $jumlah): void
    {
        for ($i = 0; $i < $jumlah; $i++) {
            $this->attempt->logs()->create(['jenis' => LogType::PindahTab, 'waktu' => now()->subSeconds(60 - $i * 10), 'dihitung' => true]);
        }
        app(ViolationService::class)->hitungUlang($this->attempt);
    }

    public function test_maafkan_satu_pelanggaran_dengan_alasan_tercatat(): void
    {
        $this->pelanggaran(2);
        $log = $this->attempt->logs()->orderBy('id')->first();

        $this->actingAs($this->dosen)->from($this->urlDosen(''))->post($this->urlDosen("pelanggaran/{$log->id}/maafkan"), ['alasan' => ''])
            ->assertSessionHasErrors('alasan');
        $this->post($this->urlDosen("pelanggaran/{$log->id}/maafkan"), ['alasan' => self::ALASAN])->assertSessionHas('status');

        $log->refresh();
        $this->assertTrue($log->dimaafkan);
        $this->assertSame($this->dosen->id, $log->dimaafkan_oleh);
        $this->assertNotNull($log->dimaafkan_pada);
        $this->assertSame(self::ALASAN, $log->alasan);
        $this->assertSame(1, $this->attempt->fresh()->jumlah_pelanggaran);

        $audit = AuditLog::where('aksi', 'pelanggaran_dimaafkan')->sole();
        $this->assertSame($this->dosen->id, $audit->user_id);
        $this->assertSame(self::ALASAN, $audit->detail['alasan']);

        // Penghitung baru langsung terlihat oleh layar ujian mahasiswa.
        $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('heartbeat'))->assertJsonPath('attempt.jumlah_pelanggaran', 1);

        // Tidak bisa dimaafkan dua kali; insiden yang tidak dihitung tidak perlu dimaafkan.
        $this->actingAs($this->dosen)->post($this->urlDosen("pelanggaran/{$log->id}/maafkan"), ['alasan' => self::ALASAN])->assertSessionHas('error');
        $insiden = $this->attempt->logs()->create(['jenis' => LogType::PerangkatBerganti, 'waktu' => now(), 'dihitung' => false]);
        $this->post($this->urlDosen("pelanggaran/{$insiden->id}/maafkan"), ['alasan' => self::ALASAN])->assertSessionHas('error');
        $this->assertSame(1, AuditLog::where('aksi', 'pelanggaran_dimaafkan')->count());
    }

    public function test_reset_memaafkan_semua_dan_pelanggaran_berikutnya_dihitung_dari_nol(): void
    {
        $this->pelanggaran(3);

        $this->actingAs($this->dosen)->post($this->urlDosen('pelanggaran/reset'), ['alasan' => self::ALASAN])->assertSessionHas('status');

        $this->assertSame(0, $this->attempt->fresh()->jumlah_pelanggaran);
        $this->assertSame(3, $this->attempt->logs()->where('dimaafkan', true)->count());
        $this->assertSame(3, AuditLog::where('aksi', 'pelanggaran_direset')->sole()->detail['jumlah']);

        $hasil = app(ViolationService::class)->catat($this->attempt->fresh(), LogType::PindahTab);
        $this->assertSame(['dihitung' => true, 'dikunci' => false], $hasil);
        $this->assertSame(1, $this->attempt->fresh()->jumlah_pelanggaran);
    }

    public function test_tambah_waktu_memperbarui_sisa_waktu_mahasiswa(): void
    {
        $sisa = $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('heartbeat'))->json('attempt.sisa_detik');

        $this->actingAs($this->dosen)->post($this->urlDosen('tambah-waktu'), ['menit' => 15, 'alasan' => 'Listrik padam 10 menit.'])
            ->assertSessionHas('status');

        $this->assertSame(15, $this->attempt->fresh()->waktu_tambahan);
        $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('heartbeat'))->assertJsonPath('attempt.sisa_detik', $sisa + 900);

        $audit = AuditLog::where('aksi', 'waktu_ditambah')->sole();
        $this->assertSame(['menit' => 15, 'alasan' => 'Listrik padam 10 menit.'], array_intersect_key($audit->detail, array_flip(['menit', 'alasan'])));

        $this->actingAs($this->dosen)->post($this->urlDosen('tambah-waktu'), ['menit' => 0, 'alasan' => 'x'])->assertSessionHasErrors(['menit', 'alasan']);
    }

    public function test_tambah_waktu_ditolak_untuk_attempt_yang_sudah_selesai(): void
    {
        $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('kirim'))->assertOk();

        $this->actingAs($this->dosen)->post($this->urlDosen('tambah-waktu'), ['menit' => 10, 'alasan' => self::ALASAN])->assertSessionHas('error');
        $this->assertSame(0, $this->attempt->fresh()->waktu_tambahan);
    }

    public function test_buka_ulang_attempt_terkunci_setelah_pelanggaran_dimaafkan(): void
    {
        $this->pelanggaran(3);
        app(ViolationService::class)->catat($this->attempt->fresh(), LogType::KeluarFullscreen);
        $this->assertSame(AttemptStatus::Terkunci, $this->attempt->fresh()->status);

        $this->actingAs($this->dosen)->post($this->urlDosen('buka-ulang'), ['menit' => 20, 'alasan' => self::ALASAN])
            ->assertSessionHas('error', 'Pelanggaran peserta ini (4) masih melebihi batas 3. Maafkan pelanggaran terlebih dahulu.');

        $this->post($this->urlDosen('pelanggaran/reset'), ['alasan' => self::ALASAN]);

        // Jadwal ujian sudah berakhir; mahasiswa tetap mendapat 20 menit sejak dibuka ulang.
        $this->travelTo($this->exam->selesaiPada()->addMinutes(5));
        $this->post($this->urlDosen('buka-ulang'), ['menit' => 20, 'alasan' => self::ALASAN])->assertSessionHas('status');

        $attempt = $this->attempt->fresh();
        $this->assertSame(AttemptStatus::Berlangsung, $attempt->status);
        $this->assertNull($attempt->selesai);
        $this->assertNull($attempt->alasan_selesai);
        $this->assertSame(20 * 60, $attempt->sisaDetik());
        $this->assertSame('terkunci', AuditLog::where('aksi', 'attempt_dibuka_ulang')->sole()->detail['status_sebelumnya']);

        $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('mulai'), ['setuju' => '1'])->assertOk();
        $this->getJson($this->urlMhs('soal'))->assertOk()->assertJsonPath('attempt.sisa_detik', 1200);
        $this->postJson($this->urlMhs('kirim'))->assertOk();
        $this->assertSame(FinishReason::Manual, $this->attempt->fresh()->alasan_selesai);
    }

    public function test_buka_ulang_ditolak_bila_masih_berlangsung_atau_nilai_sudah_dipublikasikan(): void
    {
        $this->actingAs($this->dosen)->post($this->urlDosen('buka-ulang'), ['menit' => 10, 'alasan' => self::ALASAN])->assertSessionHas('error');

        $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('kirim'))->assertOk();
        $this->attempt->result->update(['dipublikasikan_pada' => now()]);

        $this->actingAs($this->dosen)->post($this->urlDosen('buka-ulang'), ['menit' => 10, 'alasan' => self::ALASAN])
            ->assertSessionHas('error', 'Nilai peserta ini sudah dipublikasikan; attempt tidak dapat dibuka ulang.');
        $this->assertSame(AttemptStatus::Selesai, $this->attempt->fresh()->status);
    }

    public function test_esai_yang_diubah_setelah_buka_ulang_mereset_skor_lamanya(): void
    {
        $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('jawaban'), ['jawaban' => [['nomor' => $this->nomorEsai(), 'teks' => 'Jawaban awal']]])->assertOk();
        $this->postJson($this->urlMhs('kirim'))->assertOk();
        $jawaban = $this->attempt->answers()->where('question_id', $this->esai->id)->sole();
        $jawaban->update(['similarity' => 0.7, 'skor_sistem' => 7, 'skor_final' => 7, 'kata_kunci_cocok' => ['x'], 'dinilai_oleh' => $this->dosen->id, 'dinilai_pada' => now()]);

        $this->actingAs($this->dosen)->post($this->urlDosen('buka-ulang'), ['menit' => 10, 'alasan' => self::ALASAN])->assertSessionHas('status');

        // Teks sama: skor tetap.
        $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('jawaban'), ['jawaban' => [['nomor' => $this->nomorEsai(), 'teks' => 'Jawaban awal']]])->assertOk();
        $this->assertSame(7.0, $jawaban->fresh()->skor_final);

        $this->postJson($this->urlMhs('jawaban'), ['jawaban' => [['nomor' => $this->nomorEsai(), 'teks' => 'Jawaban baru yang lebih lengkap']]])->assertOk();
        $jawaban->refresh();
        $this->assertSame([null, null, null, null, null], [$jawaban->similarity, $jawaban->skor_sistem, $jawaban->skor_final, $jawaban->kata_kunci_cocok, $jawaban->dinilai_oleh]);
    }

    public function test_kunci_mahasiswa_mengirim_jawaban_tersimpan_dan_layar_membeku(): void
    {
        $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('jawaban'), ['jawaban' => [['nomor' => $this->nomorEsai(), 'teks' => 'Jawaban sementara']]])->assertOk();

        $this->actingAs($this->dosen)->post($this->urlDosen('kunci'), ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->post($this->urlDosen('kunci'), ['alasan' => 'Tertangkap memakai ponsel oleh pengawas.'])
            ->assertSessionHas('status', fn ($pesan) => str_contains($pesan, 'dikunci'));

        $attempt = $this->attempt->fresh();
        $this->assertSame(AttemptStatus::Terkunci, $attempt->status);
        $this->assertSame(FinishReason::DikunciDosen, $attempt->alasan_selesai);
        $this->assertNotNull($attempt->result, 'Jawaban tersimpan dinilai seperti kirim biasa.');
        $this->assertSame('Tertangkap memakai ponsel oleh pengawas.', AuditLog::where('aksi', 'attempt_dikunci')->sole()->detail['alasan']);

        // Permintaan berikutnya dari layar ujian (heartbeat <= 15 detik) membekukan layar.
        $this->actingAs($this->mahasiswa)->postJson($this->urlMhs('heartbeat'))
            ->assertStatus(409)
            ->assertJsonPath('attempt.status', 'terkunci')
            ->assertJsonPath('message', 'Ujian Anda dikunci oleh dosen. Jawaban yang tersimpan sudah dikirim.');

        // Tidak bisa dikunci dua kali; dapat dibuka ulang bila keliru.
        $this->actingAs($this->dosen)->post($this->urlDosen('kunci'), ['alasan' => 'Ulangi penguncian.'])->assertSessionHas('error');
        $this->post($this->urlDosen('buka-ulang'), ['menit' => 10, 'alasan' => 'Salah orang, dikonfirmasi pengawas.'])->assertSessionHas('status');
    }

    public function test_attempt_atau_log_dari_ujian_lain_ditolak(): void
    {
        $ujianLain = Exam::factory()->create(['dosen_id' => $this->dosen->id]);
        $attemptLain = $ujianLain->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create()->id, 'shuffle_seed' => 1, 'urutan_soal' => [], 'urutan_opsi' => [],
            'mulai' => now(), 'status' => AttemptStatus::Berlangsung,
        ]);
        $logLain = $attemptLain->logs()->create(['jenis' => LogType::PindahTab, 'waktu' => now(), 'dihitung' => true]);

        $this->actingAs($this->dosen);
        $this->post("/dosen/ujian/{$this->exam->id}/peserta/{$attemptLain->id}/tambah-waktu", ['menit' => 5, 'alasan' => self::ALASAN])->assertNotFound();
        $this->post($this->urlDosen("pelanggaran/{$logLain->id}/maafkan"), ['alasan' => self::ALASAN])->assertNotFound();
        $this->assertFalse(ExamLog::find($logLain->id)->dimaafkan);
    }

    public function test_halaman_detail_dan_monitor_menautkan_pengelolaan(): void
    {
        $this->pelanggaran(1);
        $this->actingAs($this->dosen)->post($this->urlDosen('pelanggaran/reset'), ['alasan' => self::ALASAN]);

        $this->get("/dosen/ujian/{$this->exam->id}/rekap/{$this->attempt->id}")->assertOk()
            ->assertSee('Kelola peserta')->assertSee('Tambah waktu')->assertDontSee('Buka ulang attempt')
            ->assertSee('Dimaafkan Dosen Pengampu')->assertSee(self::ALASAN);

        $this->getJson("/dosen/ujian/{$this->exam->id}/monitor/data")
            ->assertJsonPath('peserta.0.url_kelola', route('dosen.reports.show', [$this->exam, $this->attempt]));
    }

    private function nomorEsai(): int
    {
        return array_search($this->esai->id, $this->attempt->fresh()->urutan_soal, true) + 1;
    }
}
