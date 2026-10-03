<?php

namespace Tests\Feature\Dosen;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 4.7 / FR-06.4: koreksi cepat. Hanya jawaban yang belum dikonfirmasi dengan
 * similarity di atas ambang yang diterima massal; sisanya tetap dikoreksi manual.
 */
class EssayBulkAcceptTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Exam $exam;

    private Question $esai;

    /** @var array<string, StudentAnswer> */
    private array $a = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->dosen()->create();
        $this->exam = Exam::factory()->selesai()->create(['dosen_id' => $this->dosen->id]);
        $this->esai = Question::factory()->esai()->create([
            'exam_id' => $this->exam->id, 'bobot' => 10, 'keywords' => ['permintaan', 'controller'],
        ]);

        $this->a['tinggi'] = $this->jawaban('2301001', 0.95, ['permintaan', 'controller']);
        $this->a['tanpaKunci'] = $this->jawaban('2301002', 0.85, ['permintaan']);
        $this->a['tepatAmbang'] = $this->jawaban('2301003', 0.80, ['permintaan', 'controller']);
        $this->a['rendah'] = $this->jawaban('2301004', 0.75, ['permintaan', 'controller']);
        $this->a['belumDihitung'] = $this->jawaban('2301005', null, null);
        $this->a['sudahDikonfirmasi'] = $this->jawaban('2301006', 0.90, ['permintaan', 'controller']);
        $this->a['sudahDikonfirmasi']->update(['skor_final' => 3]);
        $this->a['berlangsung'] = $this->jawaban('2301007', 0.99, ['permintaan', 'controller'], AttemptStatus::Berlangsung);

        $this->actingAs($this->dosen);
    }

    /** @param  list<string>|null  $cocok */
    private function jawaban(string $nim, ?float $similarity, ?array $cocok, AttemptStatus $status = AttemptStatus::Selesai): StudentAnswer
    {
        $attempt = $this->exam->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create(['nim_nidn' => $nim])->id,
            'shuffle_seed' => 1, 'urutan_soal' => [$this->esai->id], 'urutan_opsi' => [],
            'mulai' => $this->exam->mulai, 'status' => $status,
            'selesai' => $status === AttemptStatus::Berlangsung ? null : now(),
            'alasan_selesai' => $status === AttemptStatus::Berlangsung ? null : FinishReason::Manual,
        ]);
        $answer = $attempt->answers()->create([
            'question_id' => $this->esai->id, 'teks_jawaban' => "Jawaban {$nim}",
            'similarity' => $similarity, 'skor_sistem' => $similarity === null ? null : round($similarity * 10, 2),
            'kata_kunci_cocok' => $cocok,
        ]);
        if ($status !== AttemptStatus::Berlangsung) {
            app(ScoringService::class)->nilaiOtomatis($attempt);
        }

        return $answer;
    }

    private function terima(array $data)
    {
        return $this->post("/dosen/ujian/{$this->exam->id}/koreksi/{$this->esai->id}/terima-massal", $data);
    }

    /** @return array<string, float|null> */
    private function skorFinal(): array
    {
        return array_map(fn (StudentAnswer $a) => $a->fresh()->skor_final, $this->a);
    }

    public function test_hanya_di_atas_ambang_dengan_semua_kata_kunci_yang_diterima(): void
    {
        $this->terima(['ambang' => '0.80', 'wajib_kata_kunci' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status', '2 rekomendasi diterima (similarity ≥ 0,80, semua kata kunci terpenuhi). 3 jawaban lain belum dikonfirmasi.');

        $this->assertSame([
            'tinggi' => 9.5, 'tanpaKunci' => null, 'tepatAmbang' => 8.0, 'rendah' => null,
            'belumDihitung' => null, 'sudahDikonfirmasi' => 3.0, 'berlangsung' => null,
        ], $this->skorFinal());

        $tinggi = $this->a['tinggi']->fresh();
        $this->assertSame($this->dosen->id, $tinggi->dinilai_oleh);
        $this->assertNotNull($tinggi->dinilai_pada);
        $this->assertSame(9.5, $tinggi->attempt->result->fresh()->skor_esai_final);

        $audit = AuditLog::where('aksi', 'skor_esai_diterima_massal')->sole();
        $this->assertSame(['ambang' => 0.8, 'wajib_kata_kunci' => true, 'jumlah' => 2], array_intersect_key($audit->detail, array_flip(['ambang', 'wajib_kata_kunci', 'jumlah'])));
        $this->assertEqualsCanonicalizing([$this->a['tinggi']->id, $this->a['tepatAmbang']->id], array_keys($audit->detail['skor']));
    }

    public function test_tanpa_syarat_kata_kunci_jawaban_kata_kunci_kurang_ikut_diterima(): void
    {
        $this->terima(['ambang' => '0.80'])->assertSessionHas('status');

        $skor = $this->skorFinal();
        $this->assertSame(8.5, $skor['tanpaKunci']);
        $this->assertNull($skor['rendah']);
        $this->assertSame(3.0, $skor['sudahDikonfirmasi'], 'Keputusan dosen tidak ditimpa.');
    }

    public function test_tidak_ada_yang_memenuhi(): void
    {
        $this->terima(['ambang' => '1'])->assertSessionHas('error');
        $this->assertDatabaseMissing('audit_logs', ['aksi' => 'skor_esai_diterima_massal']);
    }

    public function test_validasi_ambang(): void
    {
        $this->terima(['ambang' => '0.3'])->assertSessionHasErrors('ambang');
        $this->terima(['ambang' => '1.5'])->assertSessionHasErrors('ambang');
        $this->terima([])->assertSessionHasErrors('ambang');
        $this->assertSame(3.0, $this->a['sudahDikonfirmasi']->fresh()->skor_final);
        $this->assertNull($this->a['tinggi']->fresh()->skor_final);
    }

    public function test_halaman_koreksi_menampilkan_jumlah_per_ambang(): void
    {
        $this->get("/dosen/ujian/{$this->exam->id}/koreksi/{$this->esai->id}")
            ->assertOk()
            ->assertSee('Koreksi cepat')
            ->assertSee('≥ 0,80 — 2 jawaban (3 tanpa syarat kata kunci)')
            ->assertSee('≥ 0,90 — 1 jawaban (1 tanpa syarat kata kunci)');
    }

    public function test_soal_pg_ditolak(): void
    {
        $pg = Question::factory()->pg()->create(['exam_id' => $this->exam->id]);
        $this->post("/dosen/ujian/{$this->exam->id}/koreksi/{$pg->id}/terima-massal", ['ambang' => '0.8'])->assertNotFound();
    }
}
