<?php

namespace Tests\Feature\Scoring;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Task 5.3: ekspor dataset esai anonim untuk uji akurasi. */
class ExportEssayDatasetTest extends TestCase
{
    use RefreshDatabase;

    public function test_ekspor_anonim_tanpa_skor_sistem(): void
    {
        $exam = Exam::factory()->selesai()->create();
        $esai = Question::factory()->esai()->create(['exam_id' => $exam->id, 'bobot' => 10, 'kunci_esai' => 'Kunci dosen.']);
        Question::factory()->pg()->create(['exam_id' => $exam->id]);

        foreach (['Jawaban pertama.', '=1+1 jawaban aneh', '', 'Belum final.'] as $i => $teks) {
            $mhs = User::factory()->mahasiswa()->create(['nim_nidn' => '230100'.$i]);
            $attempt = $exam->attempts()->create([
                'user_id' => $mhs->id, 'shuffle_seed' => 1, 'urutan_soal' => [$esai->id], 'urutan_opsi' => [],
                'mulai' => $exam->mulai, 'status' => $i === 3 ? AttemptStatus::Berlangsung : AttemptStatus::Selesai,
                'alasan_selesai' => $i === 3 ? null : FinishReason::Manual,
            ]);
            $attempt->answers()->create(['question_id' => $esai->id, 'teks_jawaban' => $teks ?: null, 'skor_sistem' => 7.5, 'skor_final' => 8]);
        }

        $path = storage_path('framework/testing/dataset-esai.csv');
        $this->artisan('ujian:ekspor-esai', ['exam' => $exam->id, '--keluar' => $path])
            ->expectsOutputToContain('2 jawaban esai diekspor')
            ->assertSuccessful();

        $isi = file_get_contents($path);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi);
        $baris = array_map(fn ($b) => str_getcsv($b, ',', '"', ''), explode("\n", trim(substr($isi, 3))));

        $this->assertSame(['soal_id', 'bobot', 'kunci', 'jawaban_id', 'kode_mahasiswa', 'jawaban', 'skor_dosen'], $baris[0]);
        $this->assertCount(3, $baris);
        $this->assertSame(['M001', 'Jawaban pertama.', ''], array_slice($baris[1], 4));
        $this->assertSame("'=1+1 jawaban aneh", $baris[2][5]);
        $this->assertStringNotContainsString('2301000', $isi, 'NIM tidak boleh diekspor.');
        $this->assertStringNotContainsString('7.5', $isi, 'Skor sistem tidak boleh diekspor.');

        $this->artisan('ujian:ekspor-esai', ['exam' => $exam->id, '--keluar' => $path, '--sertakan-skor-final' => true])->assertSuccessful();
        $this->assertStringContainsString(",M001,\"Jawaban pertama.\",8\n", file_get_contents($path));

        unlink($path);
    }

    public function test_ujian_tidak_ada(): void
    {
        $this->artisan('ujian:ekspor-esai', ['exam' => 999])->expectsOutput('Ujian tidak ditemukan.')->assertFailed();
    }
}
