<?php

namespace Tests\Feature\Mahasiswa;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Task 2.7 / FR-02.9: IP di luar daftar ditolak saat mulai dan saat heartbeat. */
class IpAllowlistTest extends TestCase
{
    use RefreshDatabase;

    private const KAMPUS = '10.20.30.40';

    private const LUAR = '36.80.1.2';

    private Exam $exam;

    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exam = Exam::factory()->create(['mulai' => now()->subMinute()]);
        Question::factory()->pg()->create(['exam_id' => $this->exam->id]);
        $this->exam->access()->create(['ip_allowlist' => "10.20.0.0/16\n192.168.1.5"]);
        $this->mahasiswa = User::factory()->mahasiswa()->create();
        $this->actingAs($this->mahasiswa);
    }

    private function dari(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function url(string $akhiran = ''): string
    {
        return "/mahasiswa/ujian/{$this->exam->id}".$akhiran;
    }

    public function test_di_luar_jaringan_tidak_dapat_memulai(): void
    {
        $this->dari(self::LUAR)->get($this->url())->assertOk()
            ->assertSee('hanya dapat dikerjakan dari jaringan kampus')->assertDontSee('data-persetujuan-tombol', false);

        $this->dari(self::LUAR)->postJson($this->url('/mulai'), ['setuju' => '1'])
            ->assertForbidden()->assertJsonPath('kode', 'jaringan_ditolak');
        $this->dari(self::LUAR)->from($this->url())->post($this->url('/mulai'), ['setuju' => '1'])
            ->assertRedirect($this->url())->assertSessionHas('error');

        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_dalam_jaringan_dapat_memulai_dan_mengerjakan(): void
    {
        $this->dari(self::KAMPUS)->postJson($this->url('/mulai'), ['setuju' => '1'])->assertCreated();
        $this->dari('192.168.1.5')->postJson($this->url('/heartbeat'))->assertOk();
        $this->dari(self::KAMPUS)->get($this->url('/kerjakan'))->assertOk();
    }

    public function test_pindah_ke_luar_jaringan_ditolak_saat_heartbeat_dan_autosave(): void
    {
        $this->dari(self::KAMPUS)->postJson($this->url('/mulai'), ['setuju' => '1'])->assertCreated();

        $this->dari(self::LUAR)->postJson($this->url('/heartbeat'))->assertForbidden()->assertJsonPath('kode', 'jaringan_ditolak');
        $this->dari(self::LUAR)->postJson($this->url('/jawaban'), ['jawaban' => [['nomor' => 1, 'opsi' => 0]]])->assertForbidden();
        $this->dari(self::LUAR)->getJson($this->url('/soal'))->assertForbidden();
        $this->dari(self::LUAR)->get($this->url('/kerjakan'))->assertRedirect($this->url())->assertSessionHas('error');
        $this->assertSame(0, ExamAttempt::sole()->answers()->count(), 'Jawaban dari luar jaringan tidak disimpan.');

        // Perpindahan jaringan tetap tercatat sebagai insiden untuk ditinjau dosen.
        $this->assertSame(1, ExamAttempt::sole()->logs()->where('jenis', 'perangkat_berganti')->count());

        // Kembali ke jaringan kampus: lanjut seperti biasa; kirim tetap boleh (hanya memfinalkan jawaban tersimpan).
        $this->dari(self::KAMPUS)->postJson($this->url('/heartbeat'))->assertOk();
        $this->dari(self::LUAR)->postJson($this->url('/kirim'))->assertOk();
    }

    public function test_ujian_tanpa_daftar_terbuka_dari_jaringan_mana_pun(): void
    {
        $this->exam->access->update(['ip_allowlist' => null]);

        $this->dari(self::LUAR)->postJson($this->url('/mulai'), ['setuju' => '1'])->assertCreated();
    }

    public function test_dosen_mengatur_daftar_ip_dengan_validasi_per_baris(): void
    {
        $dosen = User::factory()->dosen()->create();
        $exam = Exam::factory()->draft()->create(['dosen_id' => $dosen->id]);
        $data = [
            'judul' => 'Kuis', 'mata_kuliah' => 'Web', 'mulai' => now()->addDay()->format('Y-m-d\TH:i'),
            'durasi_menit' => 30, 'batas_pelanggaran' => 3, 'acak_soal' => '1', 'acak_opsi' => '1',
        ];
        $this->actingAs($dosen);

        $this->put("/dosen/ujian/{$exam->id}", [...$data, 'ip_allowlist' => "10.0.0.0/8\nkampus.ac.id\n10.0.0.0/33"])
            ->assertSessionHasErrors(['ip_allowlist' => 'Baris 2 bukan alamat IP atau CIDR yang valid: kampus.ac.id. Baris 3 bukan alamat IP atau CIDR yang valid: 10.0.0.0/33.']);

        $this->put("/dosen/ujian/{$exam->id}", [...$data, 'ip_allowlist' => " 10.0.0.0/8 # lab\r\n\r\n192.168.1.5\n"])->assertSessionHasNoErrors();
        $this->assertSame("10.0.0.0/8\n192.168.1.5", $exam->fresh()->access->ip_allowlist);
        $this->get("/dosen/ujian/{$exam->id}")->assertSee('10.0.0.0/8, 192.168.1.5');

        $this->put("/dosen/ujian/{$exam->id}", [...$data, 'ip_allowlist' => ''])->assertSessionHasNoErrors();
        $this->assertNull($exam->fresh()->access->ip_allowlist);
        $this->get("/dosen/ujian/{$exam->id}")->assertSee('Semua jaringan');
    }
}
