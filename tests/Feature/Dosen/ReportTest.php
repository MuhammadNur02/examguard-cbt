<?php

namespace Tests\Feature\Dosen;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Enums\LogType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamResult;
use App\Models\Question;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

/**
 * FR-09.1, FR-09.2 / Task 4.10: rekap nilai, detail jawaban, ekspor Excel,
 * dan publikasi nilai final (FR-07.3).
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Exam $exam;

    private Question $pg;

    private Question $esai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->dosen()->create();
        $this->exam = Exam::factory()->selesai()->create(['dosen_id' => $this->dosen->id, 'judul' => 'UTS Web']);
        $this->pg = Question::factory()->pg(kunci: 'B')->create(['exam_id' => $this->exam->id, 'bobot' => 2, 'urutan' => 1]);
        $this->esai = Question::factory()->esai()->create(['exam_id' => $this->exam->id, 'bobot' => 8, 'urutan' => 2]);
    }

    /** Attempt final dengan PG benar/salah dan esai (skor final dosen bila diberikan). */
    private function peserta(string $nim, string $nama, bool $pgBenar, ?float $skorEsai): ExamAttempt
    {
        $attempt = $this->exam->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create(['nim_nidn' => $nim, 'nama' => $nama])->id,
            'shuffle_seed' => 1, 'urutan_soal' => [$this->esai->id, $this->pg->id], 'urutan_opsi' => [],
            'mulai' => $this->exam->mulai, 'selesai' => $this->exam->mulai->copy()->addMinutes(40),
            'status' => AttemptStatus::Selesai, 'alasan_selesai' => FinishReason::Manual, 'jumlah_pelanggaran' => 1,
        ]);
        $attempt->answers()->create([
            'question_id' => $this->pg->id,
            'option_id' => $this->pg->options()->where('label', $pgBenar ? 'B' : 'A')->value('id'),
        ]);
        $attempt->answers()->create(['question_id' => $this->esai->id, 'teks_jawaban' => 'Jawaban esai', 'skor_final' => $skorEsai]);
        $attempt->logs()->create(['jenis' => LogType::PindahTab, 'waktu' => now(), 'dihitung' => true, 'detail' => ['pemicu' => 'blur']]);
        app(ScoringService::class)->nilaiOtomatis($attempt);

        return $attempt;
    }

    public function test_rekap_menampilkan_nilai_final_dan_yang_menunggu_koreksi(): void
    {
        $this->peserta('2301001', 'Andi', true, 6.0);   // (2 + 6) / 10 × 100 = 80
        $this->peserta('2301002', 'Bunga', false, null); // esai belum dikoreksi

        $this->actingAs($this->dosen)->get("/dosen/ujian/{$this->exam->id}/rekap")
            ->assertOk()
            ->assertSeeInOrder(['2301001', 'Andi', '2', '6', '10', '80'])
            ->assertSeeInOrder(['2301002', 'Bunga', 'Menunggu koreksi esai'])
            ->assertSee('Ekspor Excel');
    }

    public function test_ekspor_excel_berisi_data_yang_sama_dengan_tampilan(): void
    {
        $this->peserta('2301001', 'Andi', true, 6.0);
        $this->peserta('2301002', '=HYPERLINK("http://contoh.invalid","klik")', false, null);

        $respons = $this->actingAs($this->dosen)->get("/dosen/ujian/{$this->exam->id}/rekap/ekspor");
        $respons->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('rekap-nilai-uts-web-', $respons->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($path, $respons->streamedContent());

        $reader = new Reader;
        $reader->open($path);
        $baris = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $baris[] = $row->toArray();
            }
        }
        $reader->close();

        $this->assertSame(['No', 'NIM', 'Nama', 'Status', 'Skor PG', 'Skor Esai', 'Skor Maksimal', 'Nilai Akhir', 'Keterangan', 'Pelanggaran', 'Dikirim', 'Dipublikasikan'], $baris[0]);
        $this->assertSame([1, '2301001', 'Andi', 'Selesai', 2, 6, 10, 80], array_slice($baris[1], 0, 8));
        $this->assertSame(['Final', 1], array_slice($baris[1], 8, 2));
        $this->assertSame('Belum', $baris[1][11]);
        $this->assertSame('=HYPERLINK("http://contoh.invalid","klik")', $baris[2][2]);
        $this->assertSame('Menunggu koreksi esai', $baris[2][8]);

        // Nama berawalan "=" ditulis sebagai teks, bukan formula (cegah injeksi formula).
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringNotContainsString('<f>', $xml);
    }

    public function test_publikasi_hanya_nilai_final_dan_terlihat_mahasiswa(): void
    {
        $final = $this->peserta('2301001', 'Andi', true, 6.0);
        $belum = $this->peserta('2301002', 'Bunga', false, null);

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$this->exam->id}/rekap/publikasikan")
            ->assertSessionHas('status', '1 nilai dipublikasikan. 1 belum final (menunggu koreksi esai atau masih mengerjakan) dan belum dipublikasikan.');

        $this->assertNotNull(ExamResult::where('attempt_id', $final->id)->value('dipublikasikan_pada'));
        $this->assertNull(ExamResult::where('attempt_id', $belum->id)->value('dipublikasikan_pada'));
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'nilai_dipublikasikan', 'subjek_id' => $this->exam->id]);

        $this->actingAs($final->user)->get('/mahasiswa/nilai')->assertSeeInOrder(['UTS Web', '80']);
        $this->actingAs($belum->user)->get('/mahasiswa/nilai')->assertSee('Belum dipublikasikan');
    }

    public function test_detail_jawaban_dan_log_pelanggaran(): void
    {
        $attempt = $this->peserta('2301001', 'Andi', false, 6.0);

        $this->actingAs($this->dosen)->get("/dosen/ujian/{$this->exam->id}/rekap/{$attempt->id}")
            ->assertOk()
            ->assertSeeInOrder(['No. 1', '(soal asli 2)', 'Jawaban esai'])
            ->assertSeeInOrder(['No. 2', '(soal asli 1)', 'Salah'])
            ->assertSee('Log pelanggaran dan insiden')
            ->assertSee('Pindah tab/jendela')
            ->assertSee('blur');
    }

    public function test_otorisasi_dan_attempt_ujian_lain(): void
    {
        $attempt = $this->peserta('2301001', 'Andi', true, 6.0);
        $ujianLain = Exam::factory()->create(['dosen_id' => $this->dosen->id]);
        $attemptLain = $ujianLain->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create()->id, 'shuffle_seed' => 1,
            'urutan_soal' => [], 'urutan_opsi' => [], 'mulai' => now(),
        ]);

        $this->actingAs($this->dosen)->get("/dosen/ujian/{$this->exam->id}/rekap/{$attemptLain->id}")->assertNotFound();

        foreach ([User::factory()->dosen()->create(), $attempt->user] as $pelaku) {
            $this->actingAs($pelaku)->get("/dosen/ujian/{$this->exam->id}/rekap")->assertForbidden();
            $this->actingAs($pelaku)->get("/dosen/ujian/{$this->exam->id}/rekap/ekspor")->assertForbidden();
            $this->actingAs($pelaku)->post("/dosen/ujian/{$this->exam->id}/rekap/publikasikan")->assertForbidden();
        }
        $this->assertNull(ExamResult::where('attempt_id', $attempt->id)->value('dipublikasikan_pada'));
    }
}
