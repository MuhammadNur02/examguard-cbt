<?php

namespace Tests\Feature\Dosen;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Enums\LogType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-06.1, FR-06.2 / Task 4.5: Live Monitor menampilkan status, jumlah
 * pelanggaran, dan pelanggaran baru lewat polling.
 */
class MonitorTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Exam $exam;

    private Question $pg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
        $this->dosen = User::factory()->dosen()->create();
        $this->exam = Exam::factory()->create(['dosen_id' => $this->dosen->id, 'mulai' => now()->subMinutes(10), 'durasi_menit' => 60, 'batas_pelanggaran' => 3]);
        $this->pg = Question::factory()->pg()->create(['exam_id' => $this->exam->id]);
        Question::factory()->esai()->create(['exam_id' => $this->exam->id]);
    }

    private function attempt(string $nim, array $atribut = []): ExamAttempt
    {
        return $this->exam->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create(['nim_nidn' => $nim, 'nama' => "Mahasiswa {$nim}"])->id,
            'shuffle_seed' => 1, 'urutan_soal' => $this->exam->questions()->pluck('id')->all(), 'urutan_opsi' => [],
            'mulai' => now()->subMinutes(5), 'status' => AttemptStatus::Berlangsung, 'terakhir_aktif' => now(),
            ...$atribut,
        ]);
    }

    private function data(int $sejak = 0)
    {
        return $this->actingAs($this->dosen)->getJson("/dosen/ujian/{$this->exam->id}/monitor/data?sejak={$sejak}")->assertOk();
    }

    public function test_status_peserta_diturunkan_dari_data_server(): void
    {
        $aktif = $this->attempt('2301001', ['jumlah_pelanggaran' => 2]);
        $aktif->answers()->create(['question_id' => $this->pg->id, 'option_id' => $this->pg->options()->value('id')]);
        $this->attempt('2301002', ['terakhir_aktif' => now()->subSeconds(config('examguard.offline_setelah_detik') + 1)]);
        $this->attempt('2301003', ['status' => AttemptStatus::Selesai, 'alasan_selesai' => FinishReason::Manual, 'selesai' => now()]);
        $this->attempt('2301004', ['status' => AttemptStatus::Terkunci, 'alasan_selesai' => FinishReason::Pelanggaran, 'selesai' => now(), 'jumlah_pelanggaran' => 4]);

        $respons = $this->data();

        $respons->assertJson(['ringkasan' => ['aktif' => 1, 'offline' => 1, 'selesai' => 1, 'terkunci' => 1]]);
        $peserta = collect($respons->json('peserta'))->keyBy('nim');
        $this->assertSame('aktif', $peserta['2301001']['status']);
        $this->assertSame(2, $peserta['2301001']['pelanggaran']);
        $this->assertSame(3, $peserta['2301001']['batas']);
        $this->assertSame(1, $peserta['2301001']['terjawab']);
        $this->assertSame(2, $peserta['2301001']['jumlah_soal']);
        $this->assertSame(50 * 60, $peserta['2301001']['sisa_detik']);
        $this->assertSame('offline', $peserta['2301002']['status']);
        $this->assertSame('selesai', $peserta['2301003']['status']);
        $this->assertSame('terkunci', $peserta['2301004']['status']);
        $this->assertSame(0, $peserta['2301004']['sisa_detik']);
    }

    public function test_pelanggaran_baru_dikirim_bertahap_tanpa_muat_ulang(): void
    {
        $attempt = $this->attempt('2301001');
        $pertama = $attempt->logs()->create(['jenis' => LogType::PindahTab, 'waktu' => now(), 'dihitung' => true]);

        $awal = $this->data()->json('pelanggaran_baru');
        $this->assertCount(1, $awal);
        $this->assertSame(['id' => $pertama->id, 'nim' => '2301001', 'jenis' => 'pindah_tab', 'jenis_label' => 'Pindah tab/jendela'], array_intersect_key($awal[0], array_flip(['id', 'nim', 'jenis', 'jenis_label'])));

        $this->assertSame([], $this->data($pertama->id)->json('pelanggaran_baru'));

        $kedua = $attempt->logs()->create(['jenis' => LogType::KeluarFullscreen, 'waktu' => now(), 'dihitung' => true]);
        $baru = $this->data($pertama->id)->json('pelanggaran_baru');
        $this->assertSame([$kedua->id], array_column($baru, 'id'));
        $this->assertSame('Keluar layar penuh', $baru[0]['jenis_label']);
    }

    public function test_pelanggaran_ujian_lain_tidak_ikut(): void
    {
        $ujianLain = Exam::factory()->create(['dosen_id' => $this->dosen->id]);
        $lain = $ujianLain->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create()->id, 'shuffle_seed' => 1,
            'urutan_soal' => [], 'urutan_opsi' => [], 'mulai' => now(),
        ]);
        $lain->logs()->create(['jenis' => LogType::PindahTab, 'waktu' => now(), 'dihitung' => true]);

        $this->assertSame([], $this->data()->json('pelanggaran_baru'));
        $this->assertSame([], $this->data()->json('peserta'));
    }

    public function test_attempt_kedaluwarsa_ditutup_saat_monitor_dibuka(): void
    {
        $attempt = $this->attempt('2301001');
        $this->travelTo($this->exam->selesaiPada()->addMinutes(1));

        $this->data()->assertJson(['ringkasan' => ['selesai' => 1, 'aktif' => 0, 'offline' => 0]]);
        $this->assertSame(FinishReason::WaktuHabis, $attempt->fresh()->alasan_selesai);
    }

    public function test_halaman_monitor_memakai_polling_paling_lama_sepuluh_detik(): void
    {
        config(['examguard.monitor_poll_detik' => 30]);

        $html = $this->actingAs($this->dosen)->get("/dosen/ujian/{$this->exam->id}/monitor")->assertOk()->getContent();

        $this->assertStringContainsString('"pollDetik":10', $html);
        $this->assertStringContainsString('Pelanggaran terbaru', $html);
    }

    public function test_hanya_dosen_pemilik(): void
    {
        foreach ([User::factory()->dosen()->create(), User::factory()->mahasiswa()->create(), User::factory()->admin()->create()] as $pelaku) {
            $this->actingAs($pelaku)->get("/dosen/ujian/{$this->exam->id}/monitor")->assertForbidden();
            $this->actingAs($pelaku)->getJson("/dosen/ujian/{$this->exam->id}/monitor/data")->assertForbidden();
        }
    }
}
