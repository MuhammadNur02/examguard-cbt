<?php

namespace Tests\Feature\Mahasiswa;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DoD Task 2.4: dua akun mendapat urutan berbeda, reload tidak mengubah urutan,
 * pemetaan ke kunci tetap benar, dan respons tidak memuat kunci.
 * Juga DoD Task 2.1: ujian tidak dapat dimulai di luar jadwal.
 */
class ShuffledQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private const RAHASIA_ESAI = 'KUNCI-RAHASIA-ESAI-XYZ';

    private const KATA_KUNCI_RAHASIA = 'katakunci-rahasia-qwe';

    private Exam $exam;

    private User $andi;

    private User $budi;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed deterministik agar tes tidak bergantung pada keberuntungan.
        $seeds = [12345, 67890, 13579];
        $this->app->instance(AttemptService::class, new AttemptService(function () use (&$seeds) {
            return array_shift($seeds);
        }));

        $this->exam = Exam::factory()->create(['mulai' => now()->subMinutes(10), 'durasi_menit' => 90]);
        for ($i = 1; $i <= 6; $i++) {
            $question = Question::factory()->pg(jumlahOpsi: 5, kunci: 'BCDABC'[$i - 1])->create([
                'exam_id' => $this->exam->id, 'urutan' => $i, 'teks' => "Soal PG nomor asli {$i}",
            ]);
            $question->options()->where('label', 'E')->update(['teks' => "Semua benar ({$i})", 'posisi_tetap' => true]);
        }
        foreach ([7, 8] as $i) {
            Question::factory()->esai()->create([
                'exam_id' => $this->exam->id, 'urutan' => $i, 'teks' => "Soal esai nomor asli {$i}",
                'kunci_esai' => self::RAHASIA_ESAI." {$i}", 'keywords' => [self::KATA_KUNCI_RAHASIA],
            ]);
        }

        $this->andi = User::factory()->mahasiswa()->create();
        $this->budi = User::factory()->mahasiswa()->create();
    }

    private function mulai(User $mahasiswa, ?Exam $exam = null)
    {
        $exam ??= $this->exam;

        return $this->actingAs($mahasiswa)->postJson("/mahasiswa/ujian/{$exam->id}/mulai", ['setuju' => '1']);
    }

    private function soal(User $mahasiswa)
    {
        return $this->actingAs($mahasiswa)->getJson("/mahasiswa/ujian/{$this->exam->id}/soal");
    }

    private function attempt(User $mahasiswa): ExamAttempt
    {
        return ExamAttempt::where('user_id', $mahasiswa->id)->sole();
    }

    public function test_dua_mahasiswa_mendapat_urutan_soal_dan_opsi_berbeda(): void
    {
        $this->mulai($this->andi)->assertCreated();
        $this->mulai($this->budi)->assertCreated();

        $a = $this->attempt($this->andi);
        $b = $this->attempt($this->budi);
        $this->assertSame(12345, $a->shuffle_seed);
        $this->assertSame(67890, $b->shuffle_seed);
        $this->assertNotSame($a->urutan_soal, $b->urutan_soal);
        $this->assertEqualsCanonicalizing($a->urutan_soal, $b->urutan_soal);

        $teksA = array_column($this->soal($this->andi)->json('soal'), 'teks');
        $teksB = array_column($this->soal($this->budi)->json('soal'), 'teks');
        $this->assertNotSame($teksA, $teksB);

        $opsiBerbeda = collect($a->urutan_opsi)->contains(fn ($ids, $qid) => $ids !== $b->urutan_opsi[$qid]);
        $this->assertTrue($opsiBerbeda, 'Urutan opsi seharusnya berbeda pada minimal satu soal.');
    }

    public function test_reload_dan_mulai_ulang_tidak_mengubah_urutan(): void
    {
        $this->mulai($this->andi)->assertCreated();
        $pertama = $this->soal($this->andi)->assertOk()->json('soal');

        $this->assertSame($pertama, $this->soal($this->andi)->json('soal'));

        // "Mulai" lagi (mis. setelah gangguan) melanjutkan attempt yang sama.
        $this->mulai($this->andi)->assertOk();
        $this->assertSame(1, ExamAttempt::count());
        $this->assertSame($pertama, $this->soal($this->andi)->json('soal'));
    }

    public function test_urutan_dapat_direkonstruksi_dari_seed_tersimpan(): void
    {
        $this->mulai($this->andi);
        $attempt = $this->attempt($this->andi);

        $this->assertSame(
            [$attempt->urutan_soal, $attempt->urutan_opsi],
            app(AttemptService::class)->susunUrutan($this->exam, $attempt->shuffle_seed),
        );
    }

    public function test_respons_soal_tidak_memuat_kunci_maupun_id_asli(): void
    {
        $this->mulai($this->andi);
        $respons = $this->soal($this->andi)->assertOk();
        $mentah = $respons->getContent();

        foreach ([self::RAHASIA_ESAI, self::KATA_KUNCI_RAHASIA, 'is_correct', 'kunci', 'keywords', 'posisi_tetap', 'question_id', 'option_id', '"label"', '"id"', 'shuffle_seed'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $mentah, "Respons memuat {$terlarang}.");
        }

        $soal = $respons->json('soal');
        $this->assertCount(8, $soal);
        foreach ($soal as $i => $item) {
            $this->assertSame($i + 1, $item['nomor']);
            if ($item['tipe'] === 'pg') {
                $this->assertSame(['nomor', 'tipe', 'teks', 'bobot', 'opsi', 'jawaban', 'ragu'], array_keys($item));
                $this->assertSame(['A', 'B', 'C', 'D', 'E'], array_column($item['opsi'], 'huruf'));
                foreach ($item['opsi'] as $opsi) {
                    $this->assertSame(['huruf', 'teks'], array_keys($opsi));
                }
            } else {
                $this->assertSame(['nomor', 'tipe', 'teks', 'bobot', 'jawaban', 'ragu'], array_keys($item));
            }
        }
    }

    public function test_posisi_tampil_terpetakan_ke_kunci_yang_benar(): void
    {
        $this->mulai($this->andi);
        $attempt = $this->attempt($this->andi);
        $service = app(AttemptService::class);

        foreach ($this->soal($this->andi)->json('soal') as $item) {
            $question = Question::firstWhere('teks', $item['teks']);
            if ($question->tipe !== QuestionType::Pg) {
                $this->assertSame(['question_id' => $question->id, 'option_id' => null], $service->petakanPosisi($attempt, $item['nomor']));

                continue;
            }

            $kunci = $question->options()->where('is_correct', true)->sole();
            $posisiKunci = array_search($kunci->teks, array_column($item['opsi'], 'teks'), true);
            $hasil = $service->petakanPosisi($attempt, $item['nomor'], $posisiKunci);
            $this->assertSame($question->id, $hasil['question_id']);
            $this->assertTrue(Option::find($hasil['option_id'])->is_correct, "Soal {$item['nomor']}: posisi kunci salah terpetakan.");

            $posisiSalah = ($posisiKunci + 1) % 5;
            $this->assertFalse(Option::find($service->petakanPosisi($attempt, $item['nomor'], $posisiSalah)['option_id'])->is_correct);
        }

        $this->assertNull($service->petakanPosisi($attempt, 0));
        $this->assertNull($service->petakanPosisi($attempt, 9));
        $this->assertNull($service->petakanPosisi($attempt, 1, 7));
        $this->assertNull($service->petakanPosisi($attempt, 1, -1));
    }

    public function test_opsi_posisi_tetap_selalu_di_tempatnya(): void
    {
        $this->mulai($this->andi);
        $this->mulai($this->budi);

        foreach ([$this->andi, $this->budi] as $mahasiswa) {
            foreach ($this->soal($mahasiswa)->json('soal') as $item) {
                if ($item['tipe'] === 'pg') {
                    $this->assertStringStartsWith('Semua benar', $item['opsi'][4]['teks']);
                }
            }
        }
    }

    public function test_tanpa_pengacakan_urutan_asli_dipertahankan(): void
    {
        $this->exam->update(['acak_soal' => false, 'acak_opsi' => false]);

        $this->mulai($this->andi);
        $soal = $this->soal($this->andi)->json('soal');

        $this->assertSame(array_map(fn ($i) => $i <= 6 ? "Soal PG nomor asli {$i}" : "Soal esai nomor asli {$i}", range(1, 8)), array_column($soal, 'teks'));
        $asli = Question::firstWhere('urutan', 1)->options()->orderBy('label')->pluck('teks')->all();
        $this->assertSame($asli, array_column($soal[0]['opsi'], 'teks'));
    }

    public function test_tidak_bisa_mulai_sebelum_jadwal(): void
    {
        $exam = Exam::factory()->akanDatang()->create();
        Question::factory()->pg()->create(['exam_id' => $exam->id]);

        $this->mulai($this->andi, $exam)->assertForbidden()->assertJsonFragment(['message' => 'Ujian belum dibuka. Ujian dimulai '.$exam->mulai->translatedFormat('d M Y H:i').' WIB.']);
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_tidak_bisa_mulai_setelah_jadwal_berakhir(): void
    {
        $exam = Exam::factory()->selesai()->create();
        Question::factory()->pg()->create(['exam_id' => $exam->id]);

        $this->mulai($this->andi, $exam)->assertForbidden()->assertJson(['message' => 'Jadwal ujian sudah berakhir.']);
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_batas_jadwal_tepat(): void
    {
        $this->travelTo($this->exam->mulai->copy()->subSecond());
        $this->mulai($this->andi)->assertForbidden();

        $this->travelTo($this->exam->mulai);
        $this->mulai($this->andi)->assertCreated();

        $this->travelTo($this->exam->selesaiPada());
        $this->mulai($this->budi)->assertForbidden();
    }

    public function test_ujian_draf_tidak_terlihat_oleh_mahasiswa(): void
    {
        $this->exam->update(['status' => 'draft']);

        $this->mulai($this->andi)->assertNotFound();
        $this->soal($this->andi)->assertNotFound();
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_persetujuan_integritas_wajib(): void
    {
        $this->actingAs($this->andi)->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['setuju' => 'Centang persetujuan integritas sebelum memulai ujian.']);
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_mahasiswa_lain_tidak_bisa_melihat_soal_tanpa_attempt_sendiri(): void
    {
        $this->mulai($this->andi);

        $this->soal($this->budi)->assertNotFound()->assertJson(['message' => 'Anda belum memulai ujian ini.']);
    }

    public function test_dosen_dan_admin_tidak_bisa_memakai_endpoint_mahasiswa(): void
    {
        foreach ([User::factory()->dosen()->create(), User::factory()->admin()->create()] as $pelaku) {
            $this->actingAs($pelaku)->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1'])->assertForbidden();
            $this->actingAs($pelaku)->getJson("/mahasiswa/ujian/{$this->exam->id}/soal")->assertForbidden();
        }
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_attempt_selesai_tidak_bisa_dilanjutkan(): void
    {
        $this->mulai($this->andi);
        $this->attempt($this->andi)->update(['status' => AttemptStatus::Selesai, 'selesai' => now()]);

        $this->mulai($this->andi)->assertStatus(409)->assertJson(['message' => 'Ujian ini sudah Anda selesaikan.']);
        $this->soal($this->andi)->assertStatus(409);
    }

    public function test_soal_tidak_diberikan_setelah_waktu_habis(): void
    {
        $this->mulai($this->andi);

        $this->travelTo($this->exam->selesaiPada()->addSecond());

        $this->soal($this->andi)->assertStatus(409)->assertJson(['message' => 'Waktu ujian sudah habis.']);
    }

    public function test_ringkasan_attempt_memuat_sisa_waktu_dari_server(): void
    {
        $this->freezeSecond();
        $this->exam->update(['mulai' => now()->subMinutes(10)]);
        $respons = $this->mulai($this->andi)->assertCreated();

        $this->assertSame(80 * 60, $respons->json('attempt.sisa_detik'));
        $this->assertSame(8, $respons->json('attempt.jumlah_soal'));
        $this->assertSame(3, $respons->json('ujian.batas_pelanggaran'));
    }
}
