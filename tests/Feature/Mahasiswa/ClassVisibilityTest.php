<?php

namespace Tests\Feature\Mahasiswa;

use App\Models\Exam;
use App\Models\Kelas;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FR-02.6: hanya mahasiswa kelas terpilih yang melihat dan dapat memulai ujian. */
class ClassVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Exam $ujianKelasA;

    private Exam $ujianTanpaKelas;

    private User $anggotaA;

    private User $anggotaB;

    protected function setUp(): void
    {
        parent::setUp();
        $kelasA = Kelas::factory()->create(['nama' => 'Kelas A']);
        $kelasB = Kelas::factory()->create(['nama' => 'Kelas B']);
        $this->anggotaA = User::factory()->mahasiswa()->create();
        $this->anggotaB = User::factory()->mahasiswa()->create();
        $kelasA->mahasiswa()->attach($this->anggotaA->id);
        $kelasB->mahasiswa()->attach($this->anggotaB->id);

        $this->ujianKelasA = Exam::factory()->create(['judul' => 'Ujian Khusus Kelas A']);
        $this->ujianKelasA->kelas()->attach($kelasA->id);
        $this->ujianTanpaKelas = Exam::factory()->create(['judul' => 'Ujian Terbuka']);
        foreach ([$this->ujianKelasA, $this->ujianTanpaKelas] as $exam) {
            Question::factory()->pg()->create(['exam_id' => $exam->id]);
        }
    }

    public function test_daftar_ujian_mengikuti_kelas(): void
    {
        $this->actingAs($this->anggotaA)->get('/mahasiswa')
            ->assertSee('Ujian Khusus Kelas A')->assertSee('Ujian Terbuka');

        $this->actingAs($this->anggotaB)->get('/mahasiswa')
            ->assertDontSee('Ujian Khusus Kelas A')->assertSee('Ujian Terbuka');
    }

    public function test_mahasiswa_kelas_lain_tidak_bisa_membuka_atau_memulai(): void
    {
        $id = $this->ujianKelasA->id;
        $this->actingAs($this->anggotaB);

        $this->get("/mahasiswa/ujian/{$id}")->assertNotFound();
        $this->get("/mahasiswa/ujian/{$id}/kerjakan")->assertNotFound();
        $this->postJson("/mahasiswa/ujian/{$id}/mulai", ['setuju' => '1'])->assertNotFound();
        $this->getJson("/mahasiswa/ujian/{$id}/soal")->assertNotFound();
        $this->postJson("/mahasiswa/ujian/{$id}/heartbeat")->assertNotFound();
        $this->assertDatabaseCount('exam_attempts', 0);

        $this->actingAs($this->anggotaA)->postJson("/mahasiswa/ujian/{$id}/mulai", ['setuju' => '1'])->assertCreated();
    }

    public function test_ujian_tanpa_kelas_terbuka_untuk_semua_mahasiswa(): void
    {
        $this->actingAs($this->anggotaB)->postJson("/mahasiswa/ujian/{$this->ujianTanpaKelas->id}/mulai", ['setuju' => '1'])->assertCreated();
    }

    public function test_dosen_menetapkan_kelas_lewat_form_dan_melihat_peringatan_bila_kosong(): void
    {
        $dosen = User::factory()->dosen()->create();
        $kelas = Kelas::firstWhere('nama', 'Kelas B');
        $data = [
            'judul' => 'Ujian Baru', 'mata_kuliah' => 'Web', 'mulai' => now()->addDay()->format('Y-m-d\TH:i'),
            'durasi_menit' => 60, 'batas_pelanggaran' => 3, 'acak_soal' => '1', 'acak_opsi' => '1',
        ];

        $this->actingAs($dosen)->post('/dosen/ujian', [...$data, 'kelas' => [$kelas->id]]);
        $exam = Exam::firstWhere('judul', 'Ujian Baru');
        $this->assertSame([$kelas->id], $exam->kelas()->pluck('classes.id')->all());

        $this->put("/dosen/ujian/{$exam->id}", [...$data, 'kelas' => [999]])->assertSessionHasErrors('kelas.0');
        $this->put("/dosen/ujian/{$exam->id}", $data)->assertSessionHasNoErrors();
        $this->assertSame(0, $exam->kelas()->count());
        $this->get("/dosen/ujian/{$exam->id}")->assertSee('terlihat oleh semua mahasiswa');
    }
}
