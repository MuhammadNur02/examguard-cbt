<?php

namespace Tests\Feature\Dosen;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Task 4.11 / FR-08.1: nilai muncul pada waktu publikasi yang ditetapkan dosen. */
class ScheduledPublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Exam $exam;

    private ExamAttempt $final;

    private ExamAttempt $belumFinal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
        $this->dosen = User::factory()->dosen()->create();
        $this->exam = Exam::factory()->selesai()->create(['dosen_id' => $this->dosen->id]);
        $pg = Question::factory()->pg(kunci: 'A')->create(['exam_id' => $this->exam->id]);
        $esai = Question::factory()->esai()->create(['exam_id' => $this->exam->id]);

        $this->final = $this->attempt([$pg->id], $pg);
        $this->belumFinal = $this->attempt([$pg->id, $esai->id], $pg, $esai);
    }

    /** @param  list<int>  $soal */
    private function attempt(array $soal, Question $pg, ?Question $esai = null): ExamAttempt
    {
        $attempt = $this->exam->attempts()->create([
            'user_id' => User::factory()->mahasiswa()->create()->id, 'shuffle_seed' => 1, 'urutan_soal' => $soal,
            'urutan_opsi' => [], 'mulai' => $this->exam->mulai, 'selesai' => now(),
            'status' => AttemptStatus::Selesai, 'alasan_selesai' => FinishReason::Manual,
        ]);
        $attempt->answers()->create(['question_id' => $pg->id, 'option_id' => $pg->options()->where('label', 'A')->value('id')]);
        if ($esai) {
            // Esai berisi menunggu koreksi dosen: nilai belum final.
            $attempt->answers()->create(['question_id' => $esai->id, 'teks_jawaban' => 'Jawaban esai']);
        }
        app(ScoringService::class)->nilaiOtomatis($attempt);

        return $attempt;
    }

    private function urlJadwal(): string
    {
        return "/dosen/ujian/{$this->exam->id}/rekap/jadwal-publikasi";
    }

    private function nilaiTerlihat(ExamAttempt $attempt): bool
    {
        return ! str_contains($this->actingAs($attempt->user)->get('/mahasiswa/nilai')->assertOk()->getContent(), 'Belum dipublikasikan');
    }

    public function test_nilai_muncul_tepat_pada_waktu_yang_dijadwalkan(): void
    {
        $waktu = now()->addHours(2);
        $this->actingAs($this->dosen)->post($this->urlJadwal(), ['waktu' => $waktu->format('Y-m-d\TH:i')])->assertSessionHas('status');
        $this->assertSame($waktu->copy()->startOfMinute()->toDateTimeString(), $this->exam->fresh()->nilai_terbit_pada->toDateTimeString());
        $this->get("/dosen/ujian/{$this->exam->id}/rekap")->assertSee('dipublikasikan otomatis');

        $this->assertFalse($this->nilaiTerlihat($this->final), 'Sebelum waktunya nilai belum terlihat.');

        // Tanpa penjadwal sekalipun, halaman nilai menerbitkan jadwal yang sudah jatuh tempo.
        $this->travelTo($waktu->copy()->startOfMinute()->addSecond());
        $this->assertTrue($this->nilaiTerlihat($this->final));

        $hasil = $this->final->result->fresh();
        $this->assertSame($waktu->copy()->startOfMinute()->toDateTimeString(), $hasil->dipublikasikan_pada->toDateTimeString());
        $this->assertNull($this->belumFinal->result->fresh()->dipublikasikan_pada, 'Nilai yang belum final tidak ikut.');
        $this->assertNull($this->exam->fresh()->nilai_terbit_pada, 'Jadwal dijalankan sekali lalu dihapus.');

        $audit = AuditLog::where('aksi', 'nilai_dipublikasikan')->sole();
        $this->assertSame([1, 1, true], [$audit->detail['jumlah'], $audit->detail['belum_final'], $audit->detail['terjadwal']]);
        $this->assertNull($audit->user_id, 'Dicatat sebagai aksi sistem, bukan atas nama mahasiswa yang membuka halaman.');
    }

    public function test_perintah_terjadwal_hanya_menerbitkan_yang_jatuh_tempo(): void
    {
        $lain = Exam::factory()->selesai()->create(['nilai_terbit_pada' => now()->addDay()]);
        $this->exam->update(['nilai_terbit_pada' => now()->subMinute()]);

        $this->artisan('nilai:terbitkan-terjadwal')->assertSuccessful();

        $this->assertNotNull($this->final->result->fresh()->dipublikasikan_pada);
        $this->assertNull($this->exam->fresh()->nilai_terbit_pada);
        $this->assertNotNull($lain->fresh()->nilai_terbit_pada);

        $terdaftar = collect(app(Schedule::class)->events())->contains(fn ($e) => str_contains((string) $e->command, 'nilai:terbitkan-terjadwal'));
        $this->assertTrue($terdaftar, 'Perintah terdaftar di penjadwal tiap menit.');
    }

    public function test_jadwal_harus_di_masa_depan_dan_dapat_dibatalkan(): void
    {
        $this->actingAs($this->dosen)->post($this->urlJadwal(), ['waktu' => now()->subHour()->format('Y-m-d\TH:i')])->assertSessionHasErrors('waktu');
        $this->post($this->urlJadwal(), ['waktu' => ''])->assertSessionHasErrors('waktu');

        $this->post($this->urlJadwal(), ['waktu' => now()->addDay()->format('Y-m-d\TH:i')]);
        $this->delete($this->urlJadwal())->assertSessionHas('status');
        $this->assertNull($this->exam->fresh()->nilai_terbit_pada);
        $this->assertSame(['publikasi_nilai_dijadwalkan', 'publikasi_nilai_dibatalkan'], AuditLog::orderBy('id')->pluck('aksi')->all());

        $this->travel(2)->days();
        $this->assertFalse($this->nilaiTerlihat($this->final), 'Jadwal yang dibatalkan tidak menerbitkan nilai.');
    }

    public function test_dosen_lain_tidak_bisa_menjadwalkan(): void
    {
        $this->actingAs(User::factory()->dosen()->create())
            ->post($this->urlJadwal(), ['waktu' => now()->addDay()->format('Y-m-d\TH:i')])->assertForbidden();
        $this->assertNull($this->exam->fresh()->nilai_terbit_pada);
    }
}
