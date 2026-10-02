<?php

namespace Tests\Feature\Dosen;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
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
 * FR-06.3 / Task 4.6: koreksi esai berdampingan, dosen menyetujui atau mengubah
 * skor rekomendasi, dan keputusan dosen yang menjadi nilai akhir.
 */
class EssayCorrectionTest extends TestCase
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
        $this->exam = Exam::factory()->selesai()->create(['dosen_id' => $this->dosen->id]);
        $this->pg = Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'bobot' => 2, 'urutan' => 1]);
        $this->esai = Question::factory()->esai()->create([
            'exam_id' => $this->exam->id, 'bobot' => 10, 'urutan' => 2,
            'kunci_esai' => 'Middleware menyaring permintaan HTTP sebelum controller.',
            'keywords' => ['permintaan', 'controller'],
        ]);
    }

    /** Attempt final: PG benar (2) dan esai dengan rekomendasi. */
    private function jawaban(string $nim, ?string $teks, ?float $similarity = null, AttemptStatus $status = AttemptStatus::Selesai): StudentAnswer
    {
        $attempt = $this->exam->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create(['nim_nidn' => $nim, 'nama' => "Mhs {$nim}"])->id,
            'shuffle_seed' => 1, 'urutan_soal' => [$this->pg->id, $this->esai->id], 'urutan_opsi' => [],
            'mulai' => $this->exam->mulai, 'status' => $status,
            'selesai' => $status === AttemptStatus::Berlangsung ? null : now(),
            'alasan_selesai' => $status === AttemptStatus::Berlangsung ? null : FinishReason::Manual,
        ]);
        $attempt->answers()->create(['question_id' => $this->pg->id, 'option_id' => $this->pg->options()->where('is_correct', true)->value('id')]);
        $answer = $attempt->answers()->create([
            'question_id' => $this->esai->id, 'teks_jawaban' => $teks,
            'similarity' => $similarity, 'skor_sistem' => $similarity === null ? null : round($similarity * 10, 2),
            'kata_kunci_cocok' => $similarity === null ? null : ['permintaan'],
        ]);
        if ($status !== AttemptStatus::Berlangsung) {
            app(ScoringService::class)->nilaiOtomatis($attempt);
        }

        return $answer->fresh();
    }

    private function url(StudentAnswer $answer, string $akhiran = ''): string
    {
        return "/dosen/ujian/{$this->exam->id}/koreksi/{$this->esai->id}{$akhiran}";
    }

    public function test_daftar_soal_esai_dengan_progres(): void
    {
        $this->jawaban('2301001', 'Jawaban A', 0.6432);
        $b = $this->jawaban('2301002', 'Jawaban B');
        $b->update(['skor_final' => 5]);

        $this->actingAs($this->dosen)->get("/dosen/ujian/{$this->exam->id}/koreksi")
            ->assertOk()
            ->assertSee('Dikonfirmasi 1/2')
            ->assertSee('Ada rekomendasi 1/2')
            ->assertSee('Hitung skor rekomendasi');
    }

    public function test_halaman_berdampingan_menampilkan_jawaban_kunci_dan_rekomendasi(): void
    {
        $answer = $this->jawaban('2301001', 'Middleware menyaring permintaan. <script>alert(1)</script>', 0.6432);

        $html = $this->actingAs($this->dosen)->get($this->url($answer))
            ->assertOk()
            ->assertSee('Jawaban mahasiswa')
            ->assertSee('Kunci dosen')
            ->assertSee('Middleware menyaring permintaan HTTP sebelum controller.')
            ->assertSee('0,64')
            ->assertSee('6,43')
            ->assertSee('0,6432 × 10')
            ->assertSee('tidak ditemukan')
            ->getContent();

        $this->assertStringContainsString('<mark class="rounded-sm bg-gold-100 px-0.5 text-ink">permintaan</mark>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_setujui_menjadikan_rekomendasi_skor_final_dan_menghitung_nilai_akhir(): void
    {
        $answer = $this->jawaban('2301001', 'Jawaban A', 0.6432);

        $this->actingAs($this->dosen)->put($this->url($answer, "/{$answer->id}"), ['aksi' => 'setujui'])
            ->assertRedirect()->assertSessionHas('status');

        $answer->refresh();
        $this->assertSame(6.43, $answer->skor_final);
        $this->assertSame($this->dosen->id, $answer->dinilai_oleh);
        $this->assertNotNull($answer->dinilai_pada);

        $hasil = ExamResult::where('attempt_id', $answer->attempt_id)->sole();
        $this->assertSame(6.43, $hasil->skor_esai_final);
        // (2 + 6,43) / 12 × 100 = 70,25
        $this->assertSame(70.25, $hasil->nilai_akhir);
    }

    public function test_simpan_perubahan_menyimpan_skor_dosen(): void
    {
        $answer = $this->jawaban('2301001', 'Jawaban A', 0.6432);

        $this->actingAs($this->dosen)->put($this->url($answer, "/{$answer->id}"), ['aksi' => 'simpan', 'skor' => '8.5']);

        $this->assertSame(8.5, $answer->fresh()->skor_final);
        $this->assertSame(6.43, $answer->fresh()->skor_sistem, 'Rekomendasi sistem tetap tersimpan.');
        $this->assertSame(87.5, ExamResult::where('attempt_id', $answer->attempt_id)->sole()->nilai_akhir);
    }

    public function test_validasi_skor(): void
    {
        $answer = $this->jawaban('2301001', 'Jawaban A', 0.6432);
        $this->actingAs($this->dosen);

        foreach (['11', '-1', 'abc', ''] as $skor) {
            $this->put($this->url($answer, "/{$answer->id}"), ['aksi' => 'simpan', 'skor' => $skor])->assertSessionHasErrors('skor');
        }
        $this->put($this->url($answer, "/{$answer->id}"), ['aksi' => 'hapus'])->assertSessionHasErrors('aksi');

        $this->assertNull($answer->fresh()->skor_final);
    }

    public function test_setujui_tanpa_rekomendasi_ditolak_tetapi_manual_boleh(): void
    {
        $answer = $this->jawaban('2301001', 'Jawaban tanpa rekomendasi');
        $this->actingAs($this->dosen);

        $this->put($this->url($answer, "/{$answer->id}"), ['aksi' => 'setujui'])->assertSessionHas('error');
        $this->assertNull($answer->fresh()->skor_final);

        $this->put($this->url($answer, "/{$answer->id}"), ['aksi' => 'simpan', 'skor' => '4']);
        $this->assertSame(4.0, $answer->fresh()->skor_final);
    }

    public function test_setelah_simpan_lanjut_ke_jawaban_berikutnya_yang_belum_dikonfirmasi(): void
    {
        $a = $this->jawaban('2301001', 'A', 0.5);
        $b = $this->jawaban('2301002', 'B', 0.5);
        $b->update(['skor_final' => 5]);
        $c = $this->jawaban('2301003', 'C', 0.5);

        $this->actingAs($this->dosen)->put($this->url($a, "/{$a->id}"), ['aksi' => 'setujui'])
            ->assertRedirect($this->url($a)."?jawaban={$c->id}");
    }

    public function test_skor_final_nol_tidak_dianggap_belum_dikonfirmasi(): void
    {
        // Regresi: perbandingan longgar 0.0 == null membuat esai kosong (final 0) terpilih.
        $kosong = $this->jawaban('2301001', null);
        $a = $this->jawaban('2301002', 'A', 0.5);
        $b = $this->jawaban('2301003', 'B', 0.5);
        $nolDariDosen = $this->jawaban('2301004', 'C', 0.5);
        $nolDariDosen->update(['skor_final' => 0]);
        $d = $this->jawaban('2301005', 'D', 0.5);

        $this->actingAs($this->dosen)->get($this->url($a))->assertOk()->assertSee('2301002 · Mhs 2301002');
        $this->assertSame(0.0, $kosong->skor_final);

        $this->put($this->url($b, "/{$b->id}"), ['aksi' => 'setujui'])
            ->assertRedirect($this->url($b)."?jawaban={$d->id}");
    }

    public function test_jawaban_attempt_yang_belum_final_tidak_bisa_dinilai(): void
    {
        $berjalan = $this->jawaban('2301001', 'Masih mengerjakan', 0.5, AttemptStatus::Berlangsung);

        $this->actingAs($this->dosen)->put($this->url($berjalan, "/{$berjalan->id}"), ['aksi' => 'setujui'])->assertNotFound();
        $this->get($this->url($berjalan)."?jawaban={$berjalan->id}")->assertNotFound();
    }

    public function test_jawaban_soal_lain_dan_dosen_lain_ditolak(): void
    {
        $answer = $this->jawaban('2301001', 'Jawaban', 0.5);
        $jawabanPg = StudentAnswer::where('question_id', $this->pg->id)->sole();

        $this->actingAs($this->dosen)->put($this->url($answer, "/{$jawabanPg->id}"), ['aksi' => 'setujui'])->assertNotFound();
        $this->get("/dosen/ujian/{$this->exam->id}/koreksi/{$this->pg->id}")->assertNotFound();

        $this->actingAs(User::factory()->dosen()->create())->get($this->url($answer))->assertForbidden();
        $this->actingAs(User::factory()->dosen()->create())->put($this->url($answer, "/{$answer->id}"), ['aksi' => 'setujui'])->assertForbidden();
        $this->assertNull($answer->fresh()->skor_final);
    }

    public function test_esai_kosong_tampil_sebagai_nol_otomatis(): void
    {
        $answer = $this->jawaban('2301001', null);

        $this->assertSame(0.0, $answer->skor_final);
        $this->actingAs($this->dosen)->get($this->url($answer)."?jawaban={$answer->id}")
            ->assertOk()->assertSee('Tidak dijawab (skor otomatis 0).');
        $this->assertSame(ExamAttempt::count(), ExamResult::whereNotNull('nilai_akhir')->count());
    }
}
