<?php

namespace Tests\Feature\Dosen;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExamManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->dosen()->create();
    }

    private function dataUjian(array $ubah = []): array
    {
        return array_merge([
            'judul' => 'UTS Basis Data',
            'mata_kuliah' => 'Basis Data',
            'mulai' => '2026-11-02T08:00',
            'durasi_menit' => 90,
            'batas_pelanggaran' => 3,
            'acak_soal' => '1',
            'acak_opsi' => '0',
        ], $ubah);
    }

    private function ujianMilik(User $dosen, array $atribut = []): Exam
    {
        return Exam::factory()->draft()->create(['dosen_id' => $dosen->id, ...$atribut]);
    }

    /** Buat attempt minimal langsung di DB (alur mulai ujian diuji di tes 2.4). */
    private function buatAttempt(Exam $exam): ExamAttempt
    {
        return $exam->attempts()->create([
            'user_id' => User::factory()->create()->id,
            'shuffle_seed' => 1, 'urutan_soal' => [], 'urutan_opsi' => [], 'mulai' => now(),
        ]);
    }

    public function test_dosen_membuat_ujian_sebagai_draf(): void
    {
        $respons = $this->actingAs($this->dosen)->post('/dosen/ujian', $this->dataUjian());

        $exam = Exam::sole();
        $respons->assertRedirect("/dosen/ujian/{$exam->id}");
        $this->assertSame($this->dosen->id, $exam->dosen_id);
        $this->assertSame(ExamStatus::Draft, $exam->status);
        $this->assertSame('2026-11-02 08:00', $exam->mulai->format('Y-m-d H:i'));
        $this->assertSame('2026-11-02 09:30', $exam->selesaiPada()->format('Y-m-d H:i'));
        $this->assertTrue($exam->acak_soal);
        $this->assertFalse($exam->acak_opsi);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function dataTidakValid(): array
    {
        return [
            'judul kosong' => [['judul' => ''], 'judul'],
            'tanggal salah' => [['mulai' => 'besok pagi'], 'mulai'],
            'durasi nol' => [['durasi_menit' => 0], 'durasi_menit'],
            'durasi terlalu panjang' => [['durasi_menit' => 601], 'durasi_menit'],
            'batas nol' => [['batas_pelanggaran' => 0], 'batas_pelanggaran'],
            'batas bukan angka' => [['batas_pelanggaran' => 'tiga'], 'batas_pelanggaran'],
        ];
    }

    #[DataProvider('dataTidakValid')]
    public function test_validasi_form_ujian(array $ubah, string $kolom): void
    {
        $this->actingAs($this->dosen)->post('/dosen/ujian', $this->dataUjian($ubah))->assertSessionHasErrors($kolom);
        $this->assertDatabaseCount('exams', 0);
    }

    public function test_dosen_mengubah_ujian_miliknya(): void
    {
        $exam = $this->ujianMilik($this->dosen);

        $this->actingAs($this->dosen)->put("/dosen/ujian/{$exam->id}", $this->dataUjian(['judul' => 'Judul Baru', 'durasi_menit' => 45]))
            ->assertRedirect("/dosen/ujian/{$exam->id}");

        $this->assertSame('Judul Baru', $exam->fresh()->judul);
        $this->assertSame(45, $exam->fresh()->durasi_menit);
    }

    /** @return array<string, array{string, string}> */
    public static function aksiKelola(): array
    {
        return [
            'lihat' => ['get', '/dosen/ujian/{id}'],
            'form ubah' => ['get', '/dosen/ujian/{id}/ubah'],
            'ubah' => ['put', '/dosen/ujian/{id}'],
            'hapus' => ['delete', '/dosen/ujian/{id}'],
            'terbitkan' => ['post', '/dosen/ujian/{id}/terbitkan'],
            'tarik' => ['post', '/dosen/ujian/{id}/tarik'],
            'form soal' => ['get', '/dosen/ujian/{id}/soal/buat'],
            'simpan soal' => ['post', '/dosen/ujian/{id}/soal'],
        ];
    }

    #[DataProvider('aksiKelola')]
    public function test_dosen_lain_admin_dan_mahasiswa_ditolak(string $metode, string $url): void
    {
        $exam = $this->ujianMilik($this->dosen, ['judul' => 'Asli']);
        $url = str_replace('{id}', (string) $exam->id, $url);

        foreach ([User::factory()->dosen()->create(), User::factory()->admin()->create(), User::factory()->mahasiswa()->create()] as $pelaku) {
            $this->actingAs($pelaku)->{$metode}($url, $this->dataUjian(['judul' => 'Diretas']))->assertForbidden();
        }

        $this->assertSame('Asli', $exam->fresh()->judul);
        $this->assertSame(ExamStatus::Draft, $exam->fresh()->status);
    }

    public function test_ujian_tidak_ada_mendapat_404(): void
    {
        $this->actingAs($this->dosen)->get('/dosen/ujian/999')->assertNotFound();
        $this->actingAs($this->dosen)->get('/dosen/ujian/abc')->assertNotFound();
    }

    public function test_tidak_bisa_terbit_tanpa_soal(): void
    {
        $exam = $this->ujianMilik($this->dosen);

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$exam->id}/terbitkan")
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'Ujian belum memiliki soal.'));
        $this->assertSame(ExamStatus::Draft, $exam->fresh()->status);
    }

    public function test_tidak_bisa_terbit_bila_kunci_tidak_valid(): void
    {
        $exam = $this->ujianMilik($this->dosen);
        $tanpaKunci = Question::factory()->pg(kunci: 'Z')->create(['exam_id' => $exam->id, 'urutan' => 1]);
        $duaKunci = Question::factory()->pg()->create(['exam_id' => $exam->id, 'urutan' => 2]);
        $duaKunci->options()->where('label', 'B')->update(['is_correct' => true]);
        Question::factory()->esai()->create(['exam_id' => $exam->id, 'urutan' => 3, 'kunci_esai' => '']);

        $this->assertSame([
            'Soal 1: harus memiliki tepat satu kunci jawaban.',
            'Soal 2: harus memiliki tepat satu kunci jawaban.',
            'Soal 3: kunci esai wajib diisi.',
        ], $exam->masalahPublikasi());
        $this->assertNotNull($tanpaKunci);

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$exam->id}/terbitkan")->assertSessionHas('error');
        $this->assertSame(ExamStatus::Draft, $exam->fresh()->status);
    }

    public function test_terbitkan_dan_tarik_kembali(): void
    {
        $exam = $this->ujianMilik($this->dosen);
        Question::factory()->pg()->create(['exam_id' => $exam->id]);
        Question::factory()->esai()->create(['exam_id' => $exam->id]);

        $this->actingAs($this->dosen)->post("/dosen/ujian/{$exam->id}/terbitkan")->assertSessionHas('status');
        $this->assertSame(ExamStatus::Published, $exam->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ujian_diterbitkan', 'subjek_id' => $exam->id]);

        $this->post("/dosen/ujian/{$exam->id}/tarik")->assertSessionHas('status');
        $this->assertSame(ExamStatus::Draft, $exam->fresh()->status);
    }

    public function test_ujian_yang_sudah_dikerjakan_terkunci(): void
    {
        $exam = $this->ujianMilik($this->dosen, ['status' => ExamStatus::Published, 'judul' => 'Asli']);
        Question::factory()->pg()->create(['exam_id' => $exam->id]);
        $this->buatAttempt($exam);

        $this->actingAs($this->dosen);
        $this->get("/dosen/ujian/{$exam->id}/ubah")->assertRedirect("/dosen/ujian/{$exam->id}")->assertSessionHas('error');
        $this->put("/dosen/ujian/{$exam->id}", $this->dataUjian(['judul' => 'Berubah']))->assertSessionHas('error');
        $this->delete("/dosen/ujian/{$exam->id}")->assertSessionHas('error');
        $this->post("/dosen/ujian/{$exam->id}/tarik")->assertSessionHas('error');

        $exam->refresh();
        $this->assertSame('Asli', $exam->judul);
        $this->assertSame(ExamStatus::Published, $exam->status);
    }

    public function test_hapus_draf_menghapus_soal_dan_opsi(): void
    {
        $exam = $this->ujianMilik($this->dosen);
        Question::factory()->pg()->create(['exam_id' => $exam->id]);

        $this->actingAs($this->dosen)->delete("/dosen/ujian/{$exam->id}")->assertRedirect('/dosen');

        $this->assertDatabaseCount('exams', 0);
        $this->assertDatabaseCount('questions', 0);
        $this->assertDatabaseCount('options', 0);
    }

    public function test_halaman_detail_menampilkan_kunci_untuk_dosen(): void
    {
        $exam = $this->ujianMilik($this->dosen);
        Question::factory()->esai()->create(['exam_id' => $exam->id, 'kunci_esai' => 'Kunci rahasia dosen']);

        $this->actingAs($this->dosen)->get("/dosen/ujian/{$exam->id}")
            ->assertOk()
            ->assertSee('Kunci rahasia dosen')
            ->assertSee('Kunci patokan');
    }
}
