<?php

namespace Tests\Feature\Scoring;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamResult;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-05.1 / Task 4.1: skor PG dihitung di backend dan sama dengan hitungan manual.
 *
 * Data uji: 5 soal PG berbobot 2; 2; 3; 1,5; 1 dan 1 soal esai berbobot 10.
 * Jawaban mahasiswa: benar, salah, benar, kosong, benar.
 * Hitungan manual: skor PG = 2 + 3 + 1 = 6; skor maksimal = 2+2+3+1,5+1+10 = 19,5.
 */
class MultipleChoiceScoringTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    /** @var list<Question> */
    private array $pg = [];

    private Question $esai;

    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exam = Exam::factory()->create(['mulai' => now()->subMinutes(5), 'durasi_menit' => 60]);
        foreach ([2, 2, 3, 1.5, 1] as $i => $bobot) {
            $this->pg[] = Question::factory()->pg(kunci: 'ABCDA'[$i])->create([
                'exam_id' => $this->exam->id, 'urutan' => $i + 1, 'bobot' => $bobot, 'teks' => 'Soal PG '.($i + 1),
            ]);
        }
        $this->esai = Question::factory()->esai()->create(['exam_id' => $this->exam->id, 'urutan' => 6, 'bobot' => 10]);
        $this->mahasiswa = User::factory()->mahasiswa()->create();
    }

    /** Jawab lewat API seperti peramban: cari posisi tampil berdasarkan teks opsi. */
    private function jawabLewatApi(array $pilihan, ?string $teksEsai = null): ExamAttempt
    {
        $this->actingAs($this->mahasiswa)->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1'])->assertCreated();
        $soal = collect($this->getJson("/mahasiswa/ujian/{$this->exam->id}/soal")->json('soal'))->keyBy('teks');

        $jawaban = [];
        foreach ($pilihan as $indeks => $label) {
            if ($label === null) {
                continue;
            }
            $question = $this->pg[$indeks];
            $teksOpsi = $question->options()->where('label', $label)->value('teks');
            $item = $soal['Soal PG '.($indeks + 1)];
            $jawaban[] = ['nomor' => $item['nomor'], 'opsi' => array_search($teksOpsi, array_column($item['opsi'], 'teks'), true)];
        }
        if ($teksEsai !== null) {
            $jawaban[] = ['nomor' => $soal[$this->esai->teks]['nomor'], 'teks' => $teksEsai];
        }
        $this->postJson("/mahasiswa/ujian/{$this->exam->id}/jawaban", ['jawaban' => $jawaban])->assertOk();
        $this->postJson("/mahasiswa/ujian/{$this->exam->id}/kirim")->assertOk();

        return ExamAttempt::sole();
    }

    public function test_skor_pg_sama_dengan_hitungan_manual(): void
    {
        // Kunci: A, B, C, D, A. Jawaban: A (benar), C (salah), C (benar), kosong, A (benar).
        $attempt = $this->jawabLewatApi(['A', 'C', 'C', null, 'A']);

        $hasil = ExamResult::sole();
        $this->assertSame($attempt->id, $hasil->attempt_id);
        $this->assertSame(6.0, $hasil->skor_pg);
        $this->assertSame(19.5, $hasil->skor_maksimal);

        $skorPerSoal = StudentAnswer::whereIn('question_id', collect($this->pg)->pluck('id'))
            ->get()->mapWithKeys(fn ($a) => [$a->question_id => $a->skor_sistem]);
        $this->assertSame([2.0, 0.0, 3.0, 1.0], [
            $skorPerSoal[$this->pg[0]->id], $skorPerSoal[$this->pg[1]->id], $skorPerSoal[$this->pg[2]->id], $skorPerSoal[$this->pg[4]->id],
        ]);
        $this->assertFalse($skorPerSoal->has($this->pg[3]->id), 'Soal yang tidak dijawab tidak membuat baris jawaban.');
        $this->assertSame(2.0, StudentAnswer::firstWhere('question_id', $this->pg[0]->id)->skor_final);
    }

    public function test_esai_kosong_otomatis_nol_dan_nilai_akhir_terhitung(): void
    {
        $this->jawabLewatApi(['A', 'C', 'C', null, 'A']);

        $hasil = ExamResult::sole();
        $this->assertSame(0.0, $hasil->skor_esai_final);
        // 6 / 19,5 x 100 = 30,769... -> 30,77
        $this->assertSame(30.77, $hasil->nilai_akhir);
    }

    public function test_esai_terisi_menunggu_konfirmasi_dosen(): void
    {
        $this->jawabLewatApi(['A', 'B', 'C', 'D', 'A'], 'Middleware menyaring permintaan HTTP.');

        $hasil = ExamResult::sole();
        $this->assertSame(9.5, $hasil->skor_pg);
        $this->assertNull($hasil->skor_esai_final);
        $this->assertNull($hasil->nilai_akhir, 'Nilai akhir menunggu esai dikonfirmasi dosen.');
    }

    public function test_ujian_tanpa_esai_langsung_bernilai_akhir(): void
    {
        $this->esai->delete();

        $this->jawabLewatApi(['A', 'B', 'C', 'D', 'A']);

        $hasil = ExamResult::sole();
        $this->assertSame(9.5, $hasil->skor_pg);
        $this->assertSame(9.5, $hasil->skor_maksimal);
        $this->assertNull($hasil->skor_esai_final);
        $this->assertSame(100.0, $hasil->nilai_akhir);
    }

    public function test_auto_submit_karena_pelanggaran_juga_dinilai(): void
    {
        $this->exam->update(['batas_pelanggaran' => 1]);
        $this->actingAs($this->mahasiswa)->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1']);
        $this->postJson("/mahasiswa/ujian/{$this->exam->id}/pelanggaran", ['jenis' => 'pindah_tab']);
        $this->travel(3)->seconds();
        $this->postJson("/mahasiswa/ujian/{$this->exam->id}/pelanggaran", ['jenis' => 'pindah_tab'])->assertJson(['dikunci' => true]);

        $hasil = ExamResult::sole();
        $this->assertSame(0.0, $hasil->skor_pg);
        $this->assertSame(19.5, $hasil->skor_maksimal);
    }

    public function test_penilaian_ulang_idempoten(): void
    {
        $attempt = $this->jawabLewatApi(['A', 'C', 'C', null, 'A']);

        app(ScoringService::class)->nilaiOtomatis($attempt);
        app(ScoringService::class)->nilaiOtomatis($attempt);

        $this->assertSame(1, ExamResult::count());
        $this->assertSame(6.0, ExamResult::sole()->skor_pg);
    }
}
