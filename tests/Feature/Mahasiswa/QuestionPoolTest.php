<?php

namespace Tests\Feature\Mahasiswa;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Task 2.8 / FR-03.6: tiap mahasiswa mendapat N dari M soal; subset tersimpan per attempt. */
class QuestionPoolTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    /** @var list<int> */
    private array $soal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exam = Exam::factory()->create(['mulai' => now()->subMinute(), 'pool_size' => 3]);
        for ($i = 1; $i <= 6; $i++) {
            Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'urutan' => $i, 'bobot' => $i]);
        }
        $this->soal = $this->exam->questions()->pluck('id')->all();
    }

    public function test_setiap_susunan_berisi_n_soal_berbeda_dari_ujian(): void
    {
        $service = app(AttemptService::class);
        $subset = [];

        foreach (range(1, 200) as $seed) {
            [$urutanSoal, $urutanOpsi] = $service->susunUrutan($this->exam, $seed);
            $this->assertCount(3, $urutanSoal);
            $this->assertCount(3, array_unique($urutanSoal));
            $this->assertSame([], array_diff($urutanSoal, $this->soal));
            $this->assertEqualsCanonicalizing($urutanSoal, array_keys($urutanOpsi), 'Opsi hanya disusun untuk soal terpilih.');
            $subset[implode(',', $this->urut($urutanSoal))] = true;
        }

        // 6C3 = 20 kemungkinan subset; 200 seed seharusnya menjangkau (hampir) semuanya.
        $this->assertGreaterThanOrEqual(18, count($subset));
        $muncul = array_values(array_unique(array_map('intval', array_merge(...array_map(fn ($k) => explode(',', $k), array_keys($subset))))));
        sort($muncul);
        $this->assertSame($this->soal, $muncul, 'Setiap soal dapat terpilih.');
    }

    public function test_tanpa_acak_soal_subset_tetap_urut_seperti_aslinya(): void
    {
        $this->exam->update(['acak_soal' => false]);
        $service = app(AttemptService::class);

        foreach (range(1, 50) as $seed) {
            [$urutanSoal] = $service->susunUrutan($this->exam->refresh(), $seed);
            $this->assertCount(3, $urutanSoal);
            $this->assertSame($this->urut($urutanSoal), $urutanSoal);
        }
    }

    public function test_tanpa_pool_atau_pool_tidak_lebih_kecil_semua_soal_dipakai(): void
    {
        $service = app(AttemptService::class);

        foreach ([null, 6, 10] as $pool) {
            $this->exam->update(['pool_size' => $pool]);
            [$urutanSoal] = $service->susunUrutan($this->exam->refresh(), 7);
            $this->assertEqualsCanonicalizing($this->soal, $urutanSoal);
        }
    }

    public function test_mahasiswa_menerima_dan_dinilai_dari_subsetnya_sendiri(): void
    {
        $seeds = [11, 12];
        $this->app->instance(AttemptService::class, new AttemptService(function () use (&$seeds) {
            return array_shift($seeds);
        }));

        foreach ([User::factory()->mahasiswa()->create(), User::factory()->mahasiswa()->create()] as $mahasiswa) {
            $this->actingAs($mahasiswa)->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1'])
                ->assertCreated()->assertJsonPath('attempt.jumlah_soal', 3);
            $this->getJson("/mahasiswa/ujian/{$this->exam->id}/soal")->assertJsonCount(3, 'soal');
            $this->postJson("/mahasiswa/ujian/{$this->exam->id}/kirim")->assertOk();
        }

        [$a, $b] = ExamAttempt::with('result')->orderBy('id')->get()->all();
        $this->assertNotEquals($this->urut($a->urutan_soal), $this->urut($b->urutan_soal), 'Subset tersimpan per attempt dan berbeda antarmahasiswa.');

        // Skor maksimal = jumlah bobot subset masing-masing; nilai akhir dalam persen.
        foreach ([$a, $b] as $attempt) {
            $this->assertSame((float) Question::whereIn('id', $attempt->urutan_soal)->sum('bobot'), $attempt->result->skor_maksimal);
        }
    }

    public function test_dosen_mengatur_pool_dan_terbit_ditolak_bila_soal_kurang(): void
    {
        $dosen = $this->exam->dosen;
        $this->exam->update(['status' => 'draft']);
        $data = [
            'judul' => 'Kuis Pool', 'mata_kuliah' => 'Web', 'mulai' => now()->addDay()->format('Y-m-d\TH:i'),
            'durasi_menit' => 30, 'batas_pelanggaran' => 3, 'acak_soal' => '1', 'acak_opsi' => '1',
        ];
        $this->actingAs($dosen);

        $this->put("/dosen/ujian/{$this->exam->id}", [...$data, 'pool_size' => '0'])->assertSessionHasErrors('pool_size');
        $this->put("/dosen/ujian/{$this->exam->id}", [...$data, 'pool_size' => '8'])->assertSessionHasNoErrors();
        $this->assertSame(8, $this->exam->fresh()->pool_size);

        $this->get("/dosen/ujian/{$this->exam->id}")->assertSee('8 dari 6 soal');
        $this->post("/dosen/ujian/{$this->exam->id}/terbitkan")
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'Jumlah soal per mahasiswa (8) melebihi jumlah soal (6).'));

        $this->put("/dosen/ujian/{$this->exam->id}", [...$data, 'pool_size' => '4']);
        $this->get("/dosen/ujian/{$this->exam->id}")->assertSee('4 dari 6 soal');
        $this->post("/dosen/ujian/{$this->exam->id}/terbitkan")->assertSessionHas('status');

        $this->put("/dosen/ujian/{$this->exam->id}", [...$data, 'pool_size' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->exam->fresh()->pool_size, 'Kosong berarti semua soal dipakai.');
    }

    public function test_pratinjau_dosen_menampilkan_n_soal(): void
    {
        $html = $this->actingAs($this->exam->dosen)->get("/dosen/ujian/{$this->exam->id}/pratinjau?seed=5")->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, '<article id="soal-'));
        $this->assertStringContainsString('dari 3', $html);
    }

    /** @param  list<int>  $ids  @return list<int> menurut urutan asli soal */
    private function urut(array $ids): array
    {
        return array_values(array_filter($this->soal, fn (int $id) => in_array($id, $ids, true)));
    }
}
