<?php

namespace Tests\Feature\Scoring;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Exceptions\NlpTidakTersedia;
use App\Jobs\ScoreEssayQuestion;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\NlpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Task 4.4: Laravel memanggil layanan NLP lewat HTTP internal (bertoken) dan
 * menyimpan skor rekomendasi = similarity × bobot (FR-05.3).
 */
class EssayScoringIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Exam $exam;

    private Question $esai;

    /** @var array<int, float> similarity palsu per id jawaban */
    private array $similarity = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nlp.url' => 'http://127.0.0.1:8001', 'services.nlp.token' => 'token-uji']);

        $this->dosen = User::factory()->dosen()->create();
        $this->exam = Exam::factory()->selesai()->create(['dosen_id' => $this->dosen->id]);
        $this->esai = Question::factory()->esai()->create([
            'exam_id' => $this->exam->id, 'bobot' => 8,
            'kunci_esai' => 'Middleware menyaring permintaan HTTP.', 'keywords' => ['permintaan', 'controller'],
        ]);
    }

    private function attemptDenganJawaban(?string $teks, AttemptStatus $status = AttemptStatus::Selesai): StudentAnswer
    {
        $attempt = $this->exam->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create()->id,
            'shuffle_seed' => 1, 'urutan_soal' => [$this->esai->id], 'urutan_opsi' => [],
            'mulai' => $this->exam->mulai, 'selesai' => $status === AttemptStatus::Berlangsung ? null : $this->exam->mulai->copy()->addMinutes(30),
            'status' => $status, 'alasan_selesai' => $status === AttemptStatus::Berlangsung ? null : FinishReason::Manual,
        ]);

        return $attempt->answers()->create(['question_id' => $this->esai->id, 'teks_jawaban' => $teks]);
    }

    private function palsukanNlp(): void
    {
        Http::fake(fn (Request $request) => Http::response([
            'hasil' => array_map(fn (array $j) => [
                'id' => $j['id'],
                'similarity' => $this->similarity[$j['id']] ?? 0.5,
                'kata_kunci_terpenuhi' => ['permintaan'],
                'kata_kunci_tidak_terpenuhi' => ['controller'],
            ], $request['jawaban']),
            'metode' => ['stemming' => true, 'korpus_idf' => 'kunci_dan_jawaban'],
        ]));
    }

    public function test_skor_rekomendasi_adalah_similarity_kali_bobot(): void
    {
        $a = $this->attemptDenganJawaban('Middleware menyaring permintaan.');
        $b = $this->attemptDenganJawaban('Tidak tahu.');
        $c = $this->attemptDenganJawaban('Middleware menyaring permintaan HTTP.');
        $this->similarity = [$a->id => 0.8165, $b->id => 0.25, $c->id => 1.0];
        $this->palsukanNlp();

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$this->exam->id}/koreksi/hitung")->assertSessionHas('status');

        // 0,8165 × 8 = 6,532 -> 6,53; 0,25 × 8 = 2; 1 × 8 = 8.
        $this->assertSame([0.8165, 6.53], [$a->fresh()->similarity, $a->fresh()->skor_sistem]);
        $this->assertSame([0.25, 2.0], [$b->fresh()->similarity, $b->fresh()->skor_sistem]);
        $this->assertSame([1.0, 8.0], [$c->fresh()->similarity, $c->fresh()->skor_sistem]);
        $this->assertSame(['permintaan'], $a->fresh()->kata_kunci_cocok);

        // Skor final tetap menunggu dosen; rekap menyimpan skor esai sistem.
        $this->assertNull($a->fresh()->skor_final);
        $hasil = ExamResult::where('attempt_id', $a->attempt_id)->sole();
        $this->assertSame(6.53, $hasil->skor_esai_sistem);
        $this->assertNull($hasil->skor_esai_final);
        $this->assertNull($hasil->nilai_akhir);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'skor_esai_dihitung', 'subjek_id' => $this->exam->id]);
    }

    public function test_permintaan_internal_bertoken_memuat_kunci_dan_seluruh_jawaban_soal(): void
    {
        $this->attemptDenganJawaban('Jawaban satu.');
        $this->attemptDenganJawaban('Jawaban dua.');
        $this->attemptDenganJawaban(null);
        $this->palsukanNlp();

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$this->exam->id}/koreksi/hitung");

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://127.0.0.1:8001/score'
            && $request->hasHeader('X-Internal-Token', 'token-uji')
            && $request['kunci'] === 'Middleware menyaring permintaan HTTP.'
            && array_column($request['jawaban'], 'teks') === ['Jawaban satu.', 'Jawaban dua.']
            && $request['kata_kunci'] === ['permintaan', 'controller']);
    }

    public function test_tidak_dihitung_selama_masih_ada_peserta_mengerjakan(): void
    {
        $this->exam->update(['mulai' => now()->subMinutes(10), 'durasi_menit' => 60]);
        $this->attemptDenganJawaban('Selesai.');
        $this->attemptDenganJawaban('Masih mengerjakan.', AttemptStatus::Berlangsung);
        Http::fake();

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$this->exam->id}/koreksi/hitung")
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'Masih ada peserta yang mengerjakan'));

        Http::assertNothingSent();
    }

    public function test_attempt_kedaluwarsa_ditutup_dulu_lalu_ikut_dihitung(): void
    {
        $jawaban = $this->attemptDenganJawaban('Lupa menekan kirim.', AttemptStatus::Berlangsung);
        $this->palsukanNlp();

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$this->exam->id}/koreksi/hitung")->assertSessionHas('status');

        $this->assertSame(FinishReason::WaktuHabis, $jawaban->attempt->fresh()->alasan_selesai);
        $this->assertSame(4.0, $jawaban->fresh()->skor_sistem);
    }

    public function test_hitung_ulang_tidak_menimpa_keputusan_dosen(): void
    {
        $jawaban = $this->attemptDenganJawaban('Jawaban.');
        $jawaban->update(['skor_final' => 7.0, 'dinilai_oleh' => $this->dosen->id]);
        $this->similarity = [$jawaban->id => 0.5];
        $this->palsukanNlp();

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$this->exam->id}/koreksi/hitung");

        $this->assertSame(4.0, $jawaban->fresh()->skor_sistem);
        $this->assertSame(7.0, $jawaban->fresh()->skor_final);
    }

    public function test_layanan_nlp_gagal_tidak_mengubah_data(): void
    {
        $jawaban = $this->attemptDenganJawaban('Jawaban.');
        Http::fake(['*' => Http::response(['detail' => 'Token internal tidak valid.'], 401)]);

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$this->exam->id}/koreksi/hitung")
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'Layanan NLP tidak dapat dihubungi'));

        $this->assertNull($jawaban->fresh()->skor_sistem);
        $this->assertNull($jawaban->fresh()->similarity);
    }

    public function test_klien_nlp_mengubah_galat_koneksi_menjadi_pengecualian_domain(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->expectException(NlpTidakTersedia::class);
        app(NlpClient::class)->skor('kunci', [['id' => 1, 'teks' => 'x']]);
    }

    public function test_job_mengabaikan_attempt_yang_belum_final(): void
    {
        $selesai = $this->attemptDenganJawaban('Final.');
        $this->attemptDenganJawaban('Belum final.', AttemptStatus::Berlangsung);
        $this->palsukanNlp();

        ScoreEssayQuestion::dispatchSync($this->esai->id);

        Http::assertSent(fn (Request $request) => array_column($request['jawaban'], 'id') === [$selesai->id]);
    }

    public function test_esai_tanpa_baris_jawaban_pada_attempt_final_diberi_nol(): void
    {
        $berisi = $this->attemptDenganJawaban('Jawaban.');
        $tanpaBaris = $this->attemptDenganJawaban('Akan dihapus.');
        $tanpaBaris->delete();
        $this->palsukanNlp();

        ScoreEssayQuestion::dispatchSync($this->esai->id);

        $nol = StudentAnswer::where('attempt_id', $tanpaBaris->attempt_id)->sole();
        $this->assertSame([0.0, 0.0], [$nol->skor_sistem, $nol->skor_final]);
        $this->assertSame(0.0, ExamResult::where('attempt_id', $tanpaBaris->attempt_id)->sole()->nilai_akhir);
        $this->assertSame(4.0, $berisi->fresh()->skor_sistem);
        Http::assertSent(fn (Request $request) => array_column($request['jawaban'], 'id') === [$berisi->id]);
    }

    public function test_hanya_dosen_pemilik_yang_bisa_memicu(): void
    {
        $this->attemptDenganJawaban('Jawaban.');
        Http::fake();

        $this->actingAs(User::factory()->dosen()->create())->post("/dosen/ujian/{$this->exam->id}/koreksi/hitung")->assertForbidden();
        $this->actingAs(User::factory()->mahasiswa()->create())->post("/dosen/ujian/{$this->exam->id}/koreksi/hitung")->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_ujian_tanpa_esai_ditolak(): void
    {
        $this->esai->delete();
        Http::fake();

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$this->exam->id}/koreksi/hitung")
            ->assertSessionHas('error', 'Ujian ini tidak memiliki soal esai.');
    }
}
