<?php

namespace Tests\Feature\Mahasiswa;

use App\Enums\LogType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 3.8: watermark (FR-04.8), deteksi perangkat berganti (FR-04.9), dan
 * penolakan perangkat seluler (FR-04.11).
 */
class DeviceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const LAPTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private const LAPTOP_LAIN = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0';

    private const PONSEL = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36';

    private Exam $exam;

    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exam = Exam::factory()->create(['mulai' => now()->subMinute()]);
        Question::factory()->pg()->create(['exam_id' => $this->exam->id]);
        $this->mahasiswa = User::factory()->mahasiswa()->create(['nama' => 'Andi Pratama', 'nim_nidn' => '2301777']);
        $this->actingAs($this->mahasiswa);
    }

    private function dari(string $ua, string $ip = '10.0.0.1'): static
    {
        return $this->withHeader('User-Agent', $ua)->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function mulai(): void
    {
        $this->dari(self::LAPTOP)->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1'])->assertCreated();
    }

    private function logPerangkat()
    {
        return ExamAttempt::sole()->logs()->where('jenis', LogType::PerangkatBerganti)->orderBy('id')->get();
    }

    public function test_layar_ujian_memuat_watermark_nama_dan_nim(): void
    {
        $this->mulai();

        $html = $this->dari(self::LAPTOP)->get("/mahasiswa/ujian/{$this->exam->id}/kerjakan")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-watermark[^>]*aria-hidden="true"|aria-hidden="true"[^>]*data-watermark/', $html);
        $this->assertStringContainsString('Andi Pratama · 2301777', $html);
        $this->assertStringContainsString('pointer-events-none', $html, 'Watermark tidak menghalangi klik.');
    }

    public function test_perangkat_berganti_dicatat_sebagai_insiden_tanpa_menambah_pelanggaran(): void
    {
        $this->mulai();

        $this->dari(self::LAPTOP)->postJson("/mahasiswa/ujian/{$this->exam->id}/heartbeat")->assertOk();
        $this->assertCount(0, $this->logPerangkat(), 'Perangkat yang sama tidak dicatat.');

        $this->dari(self::LAPTOP_LAIN, '10.0.0.9')->postJson("/mahasiswa/ujian/{$this->exam->id}/heartbeat")->assertOk();
        $this->dari(self::LAPTOP_LAIN, '10.0.0.9')->getJson("/mahasiswa/ujian/{$this->exam->id}/soal")->assertOk();

        $log = $this->logPerangkat();
        $this->assertCount(1, $log, 'Satu perubahan = satu catatan, bukan setiap permintaan.');
        $this->assertFalse($log[0]->dihitung);
        $this->assertSame([
            'ip_sebelumnya' => '10.0.0.1', 'ip_baru' => '10.0.0.9',
            'perangkat_sebelumnya' => 'Chrome · Windows', 'perangkat_baru' => 'Firefox · Linux',
            'ua_sebelumnya' => self::LAPTOP, 'ua_baru' => self::LAPTOP_LAIN,
        ], $log[0]->detail);

        $attempt = ExamAttempt::sole();
        $this->assertSame(0, $attempt->jumlah_pelanggaran);
        $this->assertSame('10.0.0.9', $attempt->ip);

        // Hanya IP yang berubah (mis. ganti jaringan) juga dicatat.
        $this->dari(self::LAPTOP_LAIN, '10.0.0.10')->postJson("/mahasiswa/ujian/{$this->exam->id}/jawaban", [
            'jawaban' => [['nomor' => 1, 'opsi' => 0]],
        ])->assertOk();
        $this->assertCount(2, $this->logPerangkat());

        // Dosen melihatnya di monitor dan detail rekap.
        $dosen = $this->exam->dosen;
        $this->actingAs($dosen)->getJson("/dosen/ujian/{$this->exam->id}/monitor/data")
            ->assertJsonFragment(['jenis' => 'perangkat_berganti', 'dihitung' => false]);
        $this->get("/dosen/ujian/{$this->exam->id}/rekap/{$attempt->id}")
            ->assertSee('IP 10.0.0.9 → 10.0.0.10');
    }

    public function test_melanjutkan_dari_perangkat_lain_lewat_mulai_juga_dicatat(): void
    {
        $this->mulai();

        $this->dari(self::LAPTOP_LAIN)->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1'])->assertOk();
        $this->assertCount(1, $this->logPerangkat());
    }

    public function test_perangkat_seluler_tidak_dapat_memulai(): void
    {
        $this->dari(self::PONSEL)->get("/mahasiswa/ujian/{$this->exam->id}")->assertOk()
            ->assertSee('Gunakan laptop atau komputer')->assertDontSee('data-persetujuan-tombol', false);

        $this->dari(self::PONSEL)->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1'])
            ->assertForbidden()->assertJsonFragment(['message' => 'Gunakan laptop atau komputer untuk mengerjakan ujian. Perangkat seluler dan tablet tidak didukung.']);

        $this->dari(self::PONSEL)->from("/mahasiswa/ujian/{$this->exam->id}")
            ->post("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1'])
            ->assertRedirect("/mahasiswa/ujian/{$this->exam->id}")->assertSessionHas('error');

        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_attempt_berjalan_tidak_bisa_dibuka_di_ponsel(): void
    {
        $this->mulai();

        $this->dari(self::PONSEL)->get("/mahasiswa/ujian/{$this->exam->id}/kerjakan")
            ->assertRedirect("/mahasiswa/ujian/{$this->exam->id}")->assertSessionHas('error');
        $this->dari(self::PONSEL)->getJson("/mahasiswa/ujian/{$this->exam->id}/soal")->assertForbidden();
    }
}
