<?php

namespace Tests\Feature\Dosen;

use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuestionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->dosen()->create();
        $this->exam = Exam::factory()->draft()->create(['dosen_id' => $this->dosen->id]);
        $this->actingAs($this->dosen);
    }

    private function dataPg(array $ubah = []): array
    {
        return array_replace_recursive([
            'tipe' => 'pg',
            'urutan' => 1,
            'bobot' => 2,
            'teks' => 'Ibu kota Indonesia adalah ...',
            'opsi' => [
                'A' => ['teks' => 'Bandung', 'tetap' => '0'],
                'B' => ['teks' => 'Surabaya', 'tetap' => '0'],
                'C' => ['teks' => 'Jakarta', 'tetap' => '0'],
                'D' => ['teks' => 'Semua salah', 'tetap' => '1'],
                'E' => ['teks' => '', 'tetap' => '0'],
            ],
            'kunci' => 'C',
        ], $ubah);
    }

    private function dataEsai(array $ubah = []): array
    {
        return array_merge([
            'tipe' => 'esai',
            'urutan' => 2,
            'bobot' => 10,
            'teks' => 'Jelaskan fungsi middleware.',
            'kunci_esai' => 'Middleware menyaring permintaan HTTP.',
            'keywords' => "penyaring, permintaan\nHTTP, penyaring ,  ",
        ], $ubah);
    }

    public function test_simpan_pg_dengan_tepat_satu_kunci_dan_posisi_tetap(): void
    {
        $this->post("/dosen/ujian/{$this->exam->id}/soal", $this->dataPg())->assertRedirect("/dosen/ujian/{$this->exam->id}");

        $question = Question::with('options')->sole();
        $this->assertSame(QuestionType::Pg, $question->tipe);
        $this->assertSame(2.0, $question->bobot);
        $this->assertSame(['A', 'B', 'C', 'D'], $question->options->pluck('label')->all());
        $this->assertSame(['C'], $question->options->where('is_correct', true)->pluck('label')->values()->all());
        $this->assertSame(['D'], $question->options->where('posisi_tetap', true)->pluck('label')->values()->all());
        $this->assertNull($question->kunci_esai);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function pgTidakValid(): array
    {
        return [
            'opsi B kosong' => [['opsi' => ['B' => ['teks' => '']]], 'opsi.B.teks'],
            'opsi melompat' => [['opsi' => ['C' => ['teks' => ''], 'D' => ['teks' => 'Ada']]], 'opsi'],
            'kunci di opsi kosong' => [['kunci' => 'E'], 'kunci'],
            'kunci tidak dikenal' => [['kunci' => 'F'], 'kunci'],
            'tanpa kunci' => [['kunci' => ''], 'kunci'],
            'bobot nol' => [['bobot' => 0], 'bobot'],
            'teks kosong' => [['teks' => ''], 'teks'],
        ];
    }

    #[DataProvider('pgTidakValid')]
    public function test_validasi_pg(array $ubah, string $kolom): void
    {
        $this->post("/dosen/ujian/{$this->exam->id}/soal", $this->dataPg($ubah))->assertSessionHasErrors($kolom);
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_simpan_esai_dengan_kata_kunci_rapi(): void
    {
        $this->post("/dosen/ujian/{$this->exam->id}/soal", $this->dataEsai())->assertRedirect();

        $question = Question::sole();
        $this->assertSame(QuestionType::Esai, $question->tipe);
        $this->assertSame('Middleware menyaring permintaan HTTP.', $question->kunci_esai);
        $this->assertSame(['penyaring', 'permintaan', 'HTTP'], $question->keywords);
        $this->assertDatabaseCount('options', 0);
    }

    public function test_esai_wajib_punya_kunci(): void
    {
        $this->post("/dosen/ujian/{$this->exam->id}/soal", $this->dataEsai(['kunci_esai' => '']))->assertSessionHasErrors('kunci_esai');
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_kata_kunci_maksimal_dua_puluh(): void
    {
        $kata = implode(',', array_map(fn ($i) => "kata{$i}", range(1, 21)));

        $this->post("/dosen/ujian/{$this->exam->id}/soal", $this->dataEsai(['keywords' => $kata]))->assertSessionHasErrors('keywords');
    }

    public function test_ubah_pg_mengganti_kunci_dan_menjaga_id_opsi(): void
    {
        $this->post("/dosen/ujian/{$this->exam->id}/soal", $this->dataPg());
        $question = Question::with('options')->sole();
        $idA = $question->options->firstWhere('label', 'A')->id;

        $this->put("/dosen/ujian/{$this->exam->id}/soal/{$question->id}", $this->dataPg([
            'tipe' => 'esai', // diabaikan: tipe tidak bisa diganti
            'kunci' => 'A',
            'opsi' => ['D' => ['teks' => '', 'tetap' => '0']],
        ]))->assertRedirect("/dosen/ujian/{$this->exam->id}");

        $question->refresh()->load('options');
        $this->assertSame(QuestionType::Pg, $question->tipe);
        $this->assertSame(['A', 'B', 'C'], $question->options->pluck('label')->all());
        $this->assertSame(['A'], $question->options->where('is_correct', true)->pluck('label')->values()->all());
        $this->assertSame($idA, $question->options->firstWhere('label', 'A')->id);
    }

    public function test_soal_milik_ujian_lain_tidak_dapat_diakses_lewat_ujian_ini(): void
    {
        $ujianLain = Exam::factory()->draft()->create(['dosen_id' => $this->dosen->id]);
        $soalLain = Question::factory()->pg()->create(['exam_id' => $ujianLain->id]);

        $this->get("/dosen/ujian/{$this->exam->id}/soal/{$soalLain->id}/ubah")->assertNotFound();
        $this->delete("/dosen/ujian/{$this->exam->id}/soal/{$soalLain->id}")->assertNotFound();
        $this->assertDatabaseHas('questions', ['id' => $soalLain->id]);
    }

    public function test_soal_tidak_bisa_diubah_saat_ujian_terbit(): void
    {
        $question = Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'teks' => 'Asli']);
        $this->exam->update(['status' => ExamStatus::Published]);

        $this->get("/dosen/ujian/{$this->exam->id}/soal/buat")->assertRedirect()->assertSessionHas('error');
        $this->post("/dosen/ujian/{$this->exam->id}/soal", $this->dataPg())->assertSessionHas('error');
        $this->put("/dosen/ujian/{$this->exam->id}/soal/{$question->id}", $this->dataPg(['teks' => 'Berubah']))->assertSessionHas('error');
        $this->delete("/dosen/ujian/{$this->exam->id}/soal/{$question->id}")->assertSessionHas('error');

        $this->assertSame('Asli', $question->fresh()->teks);
        $this->assertDatabaseCount('questions', 1);
    }

    public function test_form_soal_baru_mengusulkan_nomor_urut_berikutnya(): void
    {
        Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'urutan' => 4]);

        $this->get("/dosen/ujian/{$this->exam->id}/soal/buat?tipe=esai")
            ->assertOk()
            ->assertSee('name="urutan"', false)
            ->assertSee('value="5"', false)
            ->assertSee('Kunci jawaban patokan');
    }
}
