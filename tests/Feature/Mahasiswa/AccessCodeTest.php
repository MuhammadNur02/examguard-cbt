<?php

namespace Tests\Feature\Mahasiswa;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Task 2.6 / FR-02.8: tanpa kode akses yang benar, mahasiswa tidak bisa memulai. */
class AccessCodeTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exam = Exam::factory()->create(['mulai' => now()->subMinute()]);
        Question::factory()->pg()->create(['exam_id' => $this->exam->id]);
        $this->exam->access()->create(['kode_akses' => 'RAHASIA7']);
        $this->mahasiswa = User::factory()->mahasiswa()->create();
        $this->actingAs($this->mahasiswa);
    }

    private function mulai(?string $kode)
    {
        return $this->postJson("/mahasiswa/ujian/{$this->exam->id}/mulai", array_filter(['setuju' => '1', 'kode_akses' => $kode]));
    }

    public function test_halaman_masuk_meminta_kode_tanpa_membocorkannya(): void
    {
        $this->get("/mahasiswa/ujian/{$this->exam->id}")->assertOk()
            ->assertSee('Kode akses')->assertDontSee('RAHASIA7');
    }

    public function test_kode_kosong_atau_salah_ditolak_dan_tidak_membuat_attempt(): void
    {
        $this->mulai(null)->assertUnprocessable()->assertJsonValidationErrors('kode_akses');
        $this->mulai('SALAH')->assertUnprocessable()->assertJsonValidationErrors(['kode_akses' => 'Kode akses salah.']);
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_kode_benar_tidak_peka_huruf_dan_spasi(): void
    {
        $this->mulai(' rahasia7 ')->assertCreated();
        $this->assertDatabaseCount('exam_attempts', 1);
    }

    public function test_melanjutkan_attempt_yang_ada_tidak_meminta_kode_lagi(): void
    {
        $this->mulai('RAHASIA7')->assertCreated();
        $this->mulai(null)->assertOk();
    }

    public function test_form_biasa_kembali_dengan_galat(): void
    {
        $this->from("/mahasiswa/ujian/{$this->exam->id}")
            ->post("/mahasiswa/ujian/{$this->exam->id}/mulai", ['setuju' => '1', 'kode_akses' => 'SALAH'])
            ->assertRedirect("/mahasiswa/ujian/{$this->exam->id}")
            ->assertSessionHasErrors('kode_akses');

        // Persetujuan tetap tercentang setelah kode salah agar tidak perlu mencentang ulang.
        $this->get("/mahasiswa/ujian/{$this->exam->id}")->assertSee('data-persetujuan-centang checked', false);
    }

    public function test_percobaan_kode_salah_dibatasi(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->mulai('SALAH'.$i)->assertUnprocessable();
        }
        $this->mulai('RAHASIA7')->assertStatus(429);
        $this->assertDatabaseCount('exam_attempts', 0);

        RateLimiter::clear('kode-akses:'.$this->mahasiswa->id.':'.$this->exam->id);
        $this->mulai('RAHASIA7')->assertCreated();
    }

    public function test_dosen_mengatur_dan_menghapus_kode_lewat_form(): void
    {
        $dosen = $this->exam->dosen;
        $exam = Exam::factory()->draft()->create(['dosen_id' => $dosen->id]);
        $data = [
            'judul' => 'Kuis', 'mata_kuliah' => 'Web', 'mulai' => now()->addDay()->format('Y-m-d\TH:i'),
            'durasi_menit' => 30, 'batas_pelanggaran' => 3, 'acak_soal' => '1', 'acak_opsi' => '1',
        ];
        $this->actingAs($dosen);

        $this->put("/dosen/ujian/{$exam->id}", [...$data, 'kode_akses' => 'ab'])->assertSessionHasErrors('kode_akses');
        $this->put("/dosen/ujian/{$exam->id}", [...$data, 'kode_akses' => 'kode baru!'])->assertSessionHasErrors('kode_akses');

        $this->put("/dosen/ujian/{$exam->id}", [...$data, 'kode_akses' => ' web-24a '])->assertSessionHasNoErrors();
        $this->assertSame('WEB-24A', $exam->fresh()->access->kode_akses);
        $this->get("/dosen/ujian/{$exam->id}")->assertSee('WEB-24A');

        $this->put("/dosen/ujian/{$exam->id}", [...$data, 'kode_akses' => ''])->assertSessionHasNoErrors();
        $this->assertNull($exam->fresh()->access->kode_akses);

        $this->post('/dosen/ujian', [...$data, 'judul' => 'Baru', 'kode_akses' => 'X1Y2Z3'])->assertSessionHasNoErrors();
        $this->assertSame('X1Y2Z3', Exam::firstWhere('judul', 'Baru')->access->kode_akses);
    }
}
