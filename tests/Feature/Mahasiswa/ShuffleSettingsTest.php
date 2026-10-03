<?php

namespace Tests\Feature\Mahasiswa;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 2.5 / FR-03.5: acak soal dan acak opsi dapat dinyalakan terpisah per
 * ujian, dan opsi berposisi tetap tidak pernah berpindah. Diperiksa atas banyak
 * seed agar tidak lolos karena kebetulan.
 */
class ShuffleSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const JUMLAH_SEED = 200;

    private Exam $exam;

    /** @var list<int> ID soal menurut urutan asli */
    private array $soalAsli;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exam = Exam::factory()->draft()->create();
        for ($i = 1; $i <= 5; $i++) {
            Question::factory()->pg(jumlahOpsi: 5)->create(['exam_id' => $this->exam->id, 'urutan' => $i]);
        }
        $this->soalAsli = $this->exam->questions()->pluck('id')->all();
    }

    /** @return list<array{0: list<int>, 1: array<int, list<int>>}> */
    private function susunanUntukBanyakSeed(): array
    {
        $service = app(AttemptService::class);
        $this->exam->refresh();

        return array_map(fn (int $seed) => $service->susunUrutan($this->exam, $seed), range(1, self::JUMLAH_SEED));
    }

    /** @return list<int> */
    private function opsiAsli(int $questionId): array
    {
        return Question::find($questionId)->options()->pluck('id')->all();
    }

    public function test_acak_soal_saja_opsi_tetap_berurutan_label(): void
    {
        $this->exam->update(['acak_soal' => true, 'acak_opsi' => false]);
        $susunan = $this->susunanUntukBanyakSeed();

        foreach ($susunan as [$urutanSoal, $urutanOpsi]) {
            foreach ($urutanOpsi as $questionId => $opsi) {
                $this->assertSame($this->opsiAsli($questionId), $opsi);
            }
        }
        $this->assertGreaterThan(50, count(array_unique(array_map(fn ($s) => implode(',', $s[0]), $susunan))), 'Urutan soal tetap teracak.');
    }

    public function test_acak_opsi_saja_soal_tetap_berurutan_asli(): void
    {
        $this->exam->update(['acak_soal' => false, 'acak_opsi' => true]);
        $susunan = $this->susunanUntukBanyakSeed();

        foreach ($susunan as [$urutanSoal]) {
            $this->assertSame($this->soalAsli, $urutanSoal);
        }
        $pertama = $this->soalAsli[0];
        $this->assertGreaterThan(50, count(array_unique(array_map(fn ($s) => implode(',', $s[1][$pertama]), $susunan))), 'Urutan opsi tetap teracak.');
    }

    public function test_opsi_terkunci_di_tengah_dan_akhir_tidak_pernah_berpindah(): void
    {
        $question = Question::find($this->soalAsli[0]);
        $question->options()->whereIn('label', ['C', 'E'])->update(['posisi_tetap' => true]);
        [$a, $b, $c, $d, $e] = $this->opsiAsli($question->id);

        $posisiTerlihat = [];
        foreach ($this->susunanUntukBanyakSeed() as [, $urutanOpsi]) {
            $opsi = $urutanOpsi[$question->id];
            $this->assertSame($c, $opsi[2], 'Opsi C terkunci tetap di posisi ketiga.');
            $this->assertSame($e, $opsi[4], 'Opsi E terkunci tetap di posisi terakhir.');
            foreach ([0, 1, 3] as $posisi) {
                $posisiTerlihat[$opsi[$posisi]][$posisi] = true;
            }
        }

        // Opsi yang tidak terkunci benar-benar berpindah ke semua posisi bebas.
        foreach ([$a, $b, $d] as $id) {
            $this->assertSame([0, 1, 3], array_keys(array_filter([0 => $posisiTerlihat[$id][0] ?? false, 1 => $posisiTerlihat[$id][1] ?? false, 3 => $posisiTerlihat[$id][3] ?? false])));
        }
    }

    public function test_dosen_mengubah_pengaturan_acak_dan_mahasiswa_menerima_urutan_asli(): void
    {
        $dosen = $this->exam->dosen;
        $this->actingAs($dosen)->put("/dosen/ujian/{$this->exam->id}", [
            'judul' => $this->exam->judul, 'mata_kuliah' => $this->exam->mata_kuliah,
            'mulai' => now()->subMinute()->format('Y-m-d\TH:i'), 'durasi_menit' => 60,
            'batas_pelanggaran' => 3, 'acak_soal' => '0', 'acak_opsi' => '0',
        ])->assertSessionHasNoErrors();
        $this->get("/dosen/ujian/{$this->exam->id}")->assertSee('Tidak / Tidak');
        $this->post("/dosen/ujian/{$this->exam->id}/terbitkan")->assertSessionHas('status');

        $mahasiswa = User::factory()->mahasiswa()->create();
        $this->actingAs($mahasiswa)->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1'])->assertCreated();
        $soal = $this->getJson("/mahasiswa/ujian/{$this->exam->id}/soal")->json('soal');

        $teksAsli = Question::whereIn('id', $this->soalAsli)->orderBy('urutan')->pluck('teks')->all();
        $this->assertSame($teksAsli, array_column($soal, 'teks'));
        foreach ($soal as $i => $item) {
            $this->assertSame(Question::find($this->soalAsli[$i])->options()->pluck('teks')->all(), array_column($item['opsi'], 'teks'));
        }
    }
}
