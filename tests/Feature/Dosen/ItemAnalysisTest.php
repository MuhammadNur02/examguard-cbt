<?php

namespace Tests\Feature\Dosen;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Services\ItemAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Task 4.11 / FR-09.4: analisis butir soal (persentase benar per soal, tingkat kesulitan). */
class ItemAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Exam $exam;

    private Question $mudah;

    private Question $sukar;

    private Question $esai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->dosen()->create();
        $this->exam = Exam::factory()->selesai()->create(['dosen_id' => $this->dosen->id]);
        $this->mudah = Question::factory()->pg(jumlahOpsi: 4, kunci: 'A')->create(['exam_id' => $this->exam->id, 'urutan' => 1, 'teks' => 'Soal mudah']);
        $this->sukar = Question::factory()->pg(jumlahOpsi: 4, kunci: 'D')->create(['exam_id' => $this->exam->id, 'urutan' => 2, 'teks' => 'Soal sukar']);
        $this->esai = Question::factory()->esai()->create(['exam_id' => $this->exam->id, 'urutan' => 3, 'bobot' => 10, 'teks' => 'Soal esai']);

        // 4 peserta final: soal mudah 3/4 benar (1 kosong), soal sukar 1/4 benar.
        $this->attempt([$this->mudah->id => 'A', $this->sukar->id => 'D'], 8);
        $this->attempt([$this->mudah->id => 'A', $this->sukar->id => 'B'], 6);
        $this->attempt([$this->mudah->id => 'A', $this->sukar->id => 'B'], null);
        $this->attempt([$this->mudah->id => null, $this->sukar->id => 'C'], 2);
        // Attempt yang masih berlangsung tidak dihitung.
        $this->attempt([$this->mudah->id => 'B', $this->sukar->id => 'B'], null, AttemptStatus::Berlangsung);
    }

    /** @param  array<int, ?string>  $pilihan  label opsi per soal PG (null = kosong) */
    private function attempt(array $pilihan, ?float $skorEsai, AttemptStatus $status = AttemptStatus::Selesai): void
    {
        $attempt = $this->exam->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create()->id, 'shuffle_seed' => 1,
            'urutan_soal' => [$this->mudah->id, $this->sukar->id, $this->esai->id], 'urutan_opsi' => [],
            'mulai' => $this->exam->mulai, 'status' => $status,
            'selesai' => $status === AttemptStatus::Berlangsung ? null : now(),
            'alasan_selesai' => $status === AttemptStatus::Berlangsung ? null : FinishReason::Manual,
        ]);
        foreach ($pilihan as $questionId => $label) {
            $attempt->answers()->create([
                'question_id' => $questionId,
                'option_id' => $label ? Question::find($questionId)->options()->where('label', $label)->value('id') : null,
            ]);
        }
        $attempt->answers()->create(['question_id' => $this->esai->id, 'teks_jawaban' => 'Jawaban', 'skor_final' => $skorEsai]);
    }

    public function test_persentase_benar_kategori_dan_sebaran_opsi(): void
    {
        $hasil = collect(app(ItemAnalysisService::class)->analisis($this->exam))->keyBy(fn ($b) => $b['question']->id);

        $mudah = $hasil[$this->mudah->id];
        $this->assertSame([4, 3, 75.0, 'mudah'], [$mudah['peserta'], $mudah['benar'], $mudah['persen'], $mudah['kategori']]);
        $this->assertSame(['A' => 3, 'B' => 0, 'C' => 0, 'D' => 0], $mudah['sebaran']);
        $this->assertSame(1, $mudah['kosong']);

        $sukar = $hasil[$this->sukar->id];
        $this->assertSame([4, 1, 25.0, 'sukar'], [$sukar['peserta'], $sukar['benar'], $sukar['persen'], $sukar['kategori']]);
        $this->assertSame(['A' => 0, 'B' => 2, 'C' => 1, 'D' => 1], $sukar['sebaran']);
        $this->assertSame('B', $sukar['pengecoh_terkuat'], 'Opsi salah yang paling sering dipilih.');

        // Esai: rata-rata skor final dari jawaban yang sudah dikoreksi, dalam persen bobot.
        $esai = $hasil[$this->esai->id];
        $this->assertSame([4, 3, 53.33, 'sedang'], [$esai['peserta'], $esai['dikoreksi'], $esai['persen'], $esai['kategori']]);
        $this->assertTrue($esai['sementara'], 'Belum semua jawaban esai dikoreksi.');
        $this->assertFalse($mudah['sementara']);
    }

    public function test_halaman_analisis_dan_urutan_sering_salah(): void
    {
        // Jadwal ujian sudah lewat: attempt yang masih "berlangsung" ditutup dulu (waktu habis)
        // lalu ikut dihitung, jadi peserta menjadi 5 (soal mudah 3/5, soal sukar 1/5).
        $this->actingAs($this->dosen)->get("/dosen/ujian/{$this->exam->id}/analisis")->assertOk()
            ->assertSee('Analisis Butir Soal')->assertSee('60%')->assertSee('3/5 benar')->assertSee('20%')->assertSee('Sukar')
            ->assertSeeInOrder(['Soal mudah', 'Soal sukar', 'Soal esai']);

        $this->get("/dosen/ujian/{$this->exam->id}/analisis?urut=sulit")->assertOk()
            ->assertSeeInOrder(['Soal sukar', 'Soal esai', 'Soal mudah']);
    }

    public function test_soal_tanpa_peserta_tidak_memiliki_persentase(): void
    {
        $baru = Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'urutan' => 4]);

        $baris = collect(app(ItemAnalysisService::class)->analisis($this->exam))->firstWhere('question.id', $baru->id);
        $this->assertSame([0, null, null], [$baris['peserta'], $baris['persen'], $baris['kategori']]);
    }
}
