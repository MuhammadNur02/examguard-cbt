<?php

namespace Tests\Feature\Scoring;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Jobs\ScoreEssayQuestion;
use App\Models\Exam;
use App\Models\Question;
use App\Models\SimilarityFlag;
use App\Models\StudentAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Task 4.9 / FR-05.5: pasangan jawaban esai antarmahasiswa di atas ambang
 * ditandai untuk ditinjau dosen (bukan bukti kecurangan).
 */
class EssaySimilarityFlagTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Exam $exam;

    private Question $esai;

    /** @var list<array{a: int, b: int, skor: float}> */
    private array $pasangan = [];

    /** @var list<array<string, mixed>> */
    private array $permintaanKemiripan = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nlp.url' => 'http://127.0.0.1:8001', 'services.nlp.token' => 'token-uji']);
        $this->dosen = User::factory()->dosen()->create();
        $this->exam = Exam::factory()->selesai()->create(['dosen_id' => $this->dosen->id]);
        $this->esai = Question::factory()->esai()->create(['exam_id' => $this->exam->id, 'bobot' => 10]);

        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/kemiripan')) {
                $this->permintaanKemiripan[] = $request->data();

                return Http::response(['pasangan' => $this->pasangan]);
            }

            return Http::response(['hasil' => array_map(fn (array $j) => [
                'id' => $j['id'], 'similarity' => 0.5, 'kata_kunci_terpenuhi' => [], 'kata_kunci_tidak_terpenuhi' => [],
            ], $request['jawaban'])]);
        });
    }

    private function jawaban(string $nim, ?string $teks): StudentAnswer
    {
        $attempt = $this->exam->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create(['nim_nidn' => $nim])->id,
            'shuffle_seed' => 1, 'urutan_soal' => [$this->esai->id], 'urutan_opsi' => [],
            'mulai' => $this->exam->mulai, 'selesai' => now(), 'status' => AttemptStatus::Selesai, 'alasan_selesai' => FinishReason::Manual,
        ]);

        return $attempt->answers()->create(['question_id' => $this->esai->id, 'teks_jawaban' => $teks]);
    }

    public function test_pasangan_di_atas_ambang_disimpan_per_attempt(): void
    {
        $a = $this->jawaban('2301001', 'Middleware menyaring permintaan HTTP yang masuk.');
        $b = $this->jawaban('2301002', 'Middleware menyaring permintaan HTTP yang masuk!');
        $c = $this->jawaban('2301003', 'Route memetakan URL.');
        $this->jawaban('2301004', null);
        // Layanan NLP mengembalikan id jawaban; urutan a/b boleh terbalik.
        $this->pasangan = [['a' => $b->id, 'b' => $a->id, 'skor' => 0.9731]];

        ScoreEssayQuestion::dispatchSync($this->esai->id);

        $flag = SimilarityFlag::sole();
        $this->assertSame([$this->esai->id, $a->attempt_id, $b->attempt_id, 0.9731], [$flag->question_id, $flag->attempt_a, $flag->attempt_b, $flag->skor]);

        // Hanya jawaban berisi yang dibandingkan, dengan ambang dan batas token dari konfigurasi.
        $kiriman = $this->permintaanKemiripan[0];
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], array_column($kiriman['jawaban'], 'id'));
        $this->assertSame([0.8, 5], [$kiriman['ambang'], $kiriman['min_token']]);
    }

    public function test_penghitungan_ulang_mengganti_tanda_lama(): void
    {
        $a = $this->jawaban('2301001', 'Jawaban satu yang cukup panjang.');
        $b = $this->jawaban('2301002', 'Jawaban dua yang cukup panjang.');
        $this->pasangan = [['a' => $a->id, 'b' => $b->id, 'skor' => 0.85]];
        ScoreEssayQuestion::dispatchSync($this->esai->id);
        $this->assertSame(1, SimilarityFlag::count());

        $this->pasangan = [];
        ScoreEssayQuestion::dispatchSync($this->esai->id);
        $this->assertSame(0, SimilarityFlag::count());
    }

    public function test_satu_jawaban_tidak_perlu_dibandingkan(): void
    {
        $this->jawaban('2301001', 'Satu-satunya jawaban.');

        ScoreEssayQuestion::dispatchSync($this->esai->id);

        $this->assertSame([], $this->permintaanKemiripan);
    }

    public function test_dosen_melihat_pasangan_untuk_ditinjau(): void
    {
        $a = $this->jawaban('2301001', 'Middleware menyaring permintaan.');
        $b = $this->jawaban('2301002', 'Middleware menyaring permintaan!');
        $this->jawaban('2301003', 'Lain sama sekali.');
        SimilarityFlag::create(['question_id' => $this->esai->id, 'attempt_a' => $a->attempt_id, 'attempt_b' => $b->attempt_id, 'skor' => 0.9731]);

        $this->actingAs($this->dosen)->get("/dosen/ujian/{$this->exam->id}/koreksi")
            ->assertSee('Pasangan mirip 1');

        $this->get("/dosen/ujian/{$this->exam->id}/koreksi/{$this->esai->id}?jawaban={$a->id}")
            ->assertSee('Kemiripan antarmahasiswa')
            ->assertSee('2301001 ↔ 2301002')
            ->assertSee('0,97')
            ->assertSee('Mirip dengan 2301002 (0,97)')
            ->assertSee('bukan bukti kecurangan');
    }
}
