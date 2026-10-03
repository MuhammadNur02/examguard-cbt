<?php

namespace Tests\Feature\Dosen;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Enums\LogType;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Task 4.11 / FR-09.3: laporan rekap + pelanggaran per mahasiswa siap dicetak/disimpan sebagai PDF. */
class PrintReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_laporan_cetak_memuat_rekap_dan_pelanggaran_per_mahasiswa(): void
    {
        $dosen = User::factory()->dosen()->create(['nama' => 'Dr. Penguji']);
        $exam = Exam::factory()->selesai()->create(['dosen_id' => $dosen->id, 'judul' => 'UAS Jaringan', 'batas_pelanggaran' => 3]);
        $pg = Question::factory()->pg(kunci: 'A')->create(['exam_id' => $exam->id, 'bobot' => 4]);

        $buat = function (string $nim, string $nama, FinishReason $alasan) use ($exam, $pg) {
            $attempt = $exam->attempts()->create([
                'user_id' => User::factory()->mahasiswa()->create(['nim_nidn' => $nim, 'nama' => $nama])->id,
                'shuffle_seed' => 1, 'urutan_soal' => [$pg->id], 'urutan_opsi' => [], 'mulai' => $exam->mulai,
                'selesai' => now(), 'status' => $alasan === FinishReason::Pelanggaran ? AttemptStatus::Terkunci : AttemptStatus::Selesai,
                'alasan_selesai' => $alasan,
            ]);
            $attempt->answers()->create(['question_id' => $pg->id, 'option_id' => $pg->options()->where('label', 'A')->value('id')]);
            app(ScoringService::class)->nilaiOtomatis($attempt);

            return $attempt;
        };

        $bersih = $buat('2301001', 'Andi Bersih', FinishReason::Manual);
        $curang = $buat('2301002', 'Budi Terkunci', FinishReason::Pelanggaran);
        $curang->logs()->create(['jenis' => LogType::PindahTab, 'waktu' => now()->setTime(9, 15, 2), 'dihitung' => true, 'detail' => ['pemicu' => 'blur']]);
        $curang->logs()->create([
            'jenis' => LogType::KeluarFullscreen, 'waktu' => now()->setTime(9, 20, 0), 'dihitung' => true,
            'dimaafkan' => true, 'dimaafkan_oleh' => $dosen->id, 'dimaafkan_pada' => now(), 'alasan' => 'Laptop restart sendiri',
        ]);
        $curang->logs()->create(['jenis' => LogType::PerangkatBerganti, 'waktu' => now()->setTime(9, 25, 0), 'dihitung' => false,
            'detail' => ['ip_sebelumnya' => '10.0.0.1', 'ip_baru' => '10.0.0.2']]);

        $html = $this->actingAs($dosen)->get("/dosen/ujian/{$exam->id}/rekap/cetak")->assertOk()
            ->assertSee('UAS Jaringan')->assertSee('Dr. Penguji')
            ->assertSee('Cetak / Simpan PDF')
            ->assertSeeInOrder(['2301001', 'Andi Bersih', '100', '2301002', 'Budi Terkunci'])
            ->assertSee('Laporan pelanggaran per mahasiswa')
            ->assertSee('09:15:02')->assertSee('Pindah tab/jendela')->assertSee('blur')
            ->assertSee('Dimaafkan: Laptop restart sendiri')
            ->assertSee('IP 10.0.0.1 → 10.0.0.2')
            ->assertSee('Batas pelanggaran terlampaui')
            ->getContent();

        // Mahasiswa tanpa catatan tidak mendapat blok pelanggaran kosong; kunci jawaban tidak dicetak.
        $this->assertSame(1, substr_count($html, 'data-blok-pelanggaran'));
        $this->assertStringContainsString('Tidak ada pelanggaran: 2301001', $html);
        $this->assertNotNull($bersih);
    }

    public function test_ujian_tanpa_peserta_tetap_dapat_dicetak(): void
    {
        $dosen = User::factory()->dosen()->create();
        $exam = Exam::factory()->create(['dosen_id' => $dosen->id]);

        $this->actingAs($dosen)->get("/dosen/ujian/{$exam->id}/rekap/cetak")->assertOk()->assertSee('Belum ada peserta');
    }
}
