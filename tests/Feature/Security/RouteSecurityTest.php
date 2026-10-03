<?php

namespace Tests\Feature\Security;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Exam;
use App\Models\Kelas;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Task 5.2: pemeriksaan sistematis atas SEMUA rute terdaftar, bukan contoh
 * per fitur: autentikasi, otorisasi per peran, kepemilikan ujian, dan CSRF.
 */
class RouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dosen;

    private User $mahasiswa;

    /** @var array<string, int> */
    private array $parameter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->dosen = User::factory()->dosen()->create();
        $this->mahasiswa = User::factory()->mahasiswa()->create();

        $exam = Exam::factory()->create(['dosen_id' => $this->dosen->id]);
        $pg = Question::factory()->pg()->create(['exam_id' => $exam->id]);
        $esai = Question::factory()->esai()->create(['exam_id' => $exam->id]);
        $attempt = $exam->attempts()->create([
            'user_id' => $this->mahasiswa->id, 'shuffle_seed' => 1, 'urutan_soal' => [$pg->id, $esai->id],
            'urutan_opsi' => [], 'mulai' => now(), 'status' => AttemptStatus::Selesai,
            'alasan_selesai' => FinishReason::Manual, 'selesai' => now(),
        ]);
        $answer = $attempt->answers()->create(['question_id' => $esai->id, 'teks_jawaban' => 'Jawaban']);

        $this->parameter = [
            'exam' => $exam->id, 'question' => $esai->id, 'answer' => $answer->id,
            'attempt' => $attempt->id, 'user' => User::factory()->create()->id,
            'kelas' => Kelas::create(['nama' => 'Kelas Uji Keamanan'])->id,
        ];
    }

    /** @return list<array{RouteDefinition, string, string}> [rute, metode, url] */
    private function ruteBerawalan(string ...$awalan): array
    {
        $hasil = [];
        foreach (Route::getRoutes() as $rute) {
            $nama = (string) $rute->getName();
            if (! in_array(explode('.', $nama)[0], $awalan, true)) {
                continue;
            }
            $parameter = array_intersect_key($this->parameter, array_flip($rute->parameterNames()));
            $hasil[] = [$rute, $rute->methods()[0], route($nama, $parameter, false)];
        }
        $this->assertNotEmpty($hasil);

        return $hasil;
    }

    private function kirim(string $metode, string $url)
    {
        return $this->call($metode, $url, ['aksi' => 'setujui', 'aktif' => '0']);
    }

    public function test_semua_rute_berperan_mewajibkan_login(): void
    {
        foreach ($this->ruteBerawalan('admin', 'dosen', 'mahasiswa') as [$rute, $metode, $url]) {
            $this->kirim($metode, $url)->assertRedirect('/login');
        }
    }

    public function test_semua_rute_admin_menolak_dosen_dan_mahasiswa(): void
    {
        foreach ($this->ruteBerawalan('admin') as [, $metode, $url]) {
            foreach ([$this->dosen, $this->mahasiswa] as $pelaku) {
                $this->actingAs($pelaku)->kirim($metode, $url)->assertForbidden();
            }
        }
    }

    public function test_semua_rute_dosen_menolak_admin_dan_mahasiswa(): void
    {
        foreach ($this->ruteBerawalan('dosen') as [, $metode, $url]) {
            foreach ([$this->admin, $this->mahasiswa] as $pelaku) {
                $this->actingAs($pelaku)->kirim($metode, $url)->assertForbidden();
            }
        }
    }

    public function test_semua_rute_ujian_dosen_menolak_dosen_bukan_pemilik(): void
    {
        $dosenLain = User::factory()->dosen()->create();

        foreach ($this->ruteBerawalan('dosen') as [$rute, $metode, $url]) {
            if (in_array('exam', $rute->parameterNames(), true)) {
                $this->actingAs($dosenLain)->kirim($metode, $url)->assertForbidden();
            }
        }
    }

    public function test_semua_rute_mahasiswa_menolak_admin_dan_dosen(): void
    {
        foreach ($this->ruteBerawalan('mahasiswa') as [, $metode, $url]) {
            foreach ([$this->admin, $this->dosen] as $pelaku) {
                $this->actingAs($pelaku)->kirim($metode, $url)->assertForbidden();
            }
        }
    }

    public function test_semua_rute_pengubah_data_menolak_permintaan_tanpa_token_csrf(): void
    {
        // Laravel melewati pemeriksaan CSRF saat env "testing"; aktifkan untuk tes ini.
        $this->app['env'] = 'local';
        $diperiksa = 0;

        foreach (Route::getRoutes() as $rute) {
            $metode = $rute->methods()[0];
            if (in_array($metode, ['GET', 'HEAD', 'OPTIONS'], true) || ! $rute->getName()) {
                continue;
            }
            $parameter = array_intersect_key($this->parameter, array_flip($rute->parameterNames()));
            $url = route($rute->getName(), $parameter, false);

            $this->actingAs($this->admin)->call($metode, $url)->assertStatus(419);
            $this->withHeader('Sec-Fetch-Site', 'cross-site')->call($metode, $url)->assertStatus(419);
            $diperiksa++;
        }

        // 31 rute saat ini: 11 admin, 13 dosen, 5 mahasiswa, login, logout.
        $this->assertGreaterThanOrEqual(31, $diperiksa);
    }

    public function test_content_security_policy_aktif_saat_debug_mati(): void
    {
        config(['app.debug' => false]);

        $this->get('/login')->assertHeader('Content-Security-Policy', SecurityHeaders::CSP);
        $this->assertStringContainsString("script-src 'self'", SecurityHeaders::CSP);
        $this->assertStringContainsString("frame-ancestors 'none'", SecurityHeaders::CSP);
    }

    public function test_batas_laju_endpoint_ujian(): void
    {
        config(['examguard.batas_permintaan_per_menit' => 3]);
        $exam = Exam::factory()->create(['mulai' => now()->subMinute()]);
        Question::factory()->pg()->create(['exam_id' => $exam->id]);
        $mhs = User::factory()->mahasiswa()->create();
        $this->actingAs($mhs)->postJson("/mahasiswa/ujian/{$exam->id}/mulai", ['setuju' => '1'])->assertCreated();

        $this->postJson("/mahasiswa/ujian/{$exam->id}/heartbeat")->assertOk();
        $this->postJson("/mahasiswa/ujian/{$exam->id}/heartbeat")->assertOk();
        $this->postJson("/mahasiswa/ujian/{$exam->id}/heartbeat")->assertStatus(429);
    }
}
