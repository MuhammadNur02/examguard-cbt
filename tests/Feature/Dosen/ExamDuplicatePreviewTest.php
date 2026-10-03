<?php

namespace Tests\Feature\Dosen;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\Kelas;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Task 2.6: duplikat ujian (FR-02.5) dan pratinjau sebagai mahasiswa (FR-02.7). */
class ExamDuplicatePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->dosen()->create();
        $this->exam = Exam::factory()->create([
            'dosen_id' => $this->dosen->id, 'judul' => 'UTS Web', 'acak_soal' => false, 'acak_opsi' => true,
            'durasi_menit' => 75, 'batas_pelanggaran' => 4,
        ]);
        $pg = Question::factory()->pg(jumlahOpsi: 4, kunci: 'C')->create(['exam_id' => $this->exam->id, 'urutan' => 1, 'teks' => 'Soal PG satu']);
        $pg->options()->where('label', 'D')->update(['posisi_tetap' => true, 'teks' => 'Semua salah']);
        Question::factory()->esai()->create([
            'exam_id' => $this->exam->id, 'urutan' => 2, 'teks' => 'Soal esai dua',
            'kunci_esai' => 'KUNCI-ESAI-RAHASIA', 'keywords' => ['kata-rahasia'],
        ]);
        $this->exam->kelas()->attach(Kelas::factory()->create()->id);
        $this->exam->access()->create(['kode_akses' => 'LAMA123', 'ip_allowlist' => '10.0.0.0/8']);
        $this->actingAs($this->dosen);
    }

    public function test_duplikat_menyalin_semua_soal_dan_opsi_sebagai_draf(): void
    {
        $this->post("/dosen/ujian/{$this->exam->id}/duplikat")->assertRedirect();

        $salinan = Exam::where('id', '!=', $this->exam->id)->sole();
        $this->assertSame('UTS Web (salinan)', $salinan->judul);
        $this->assertSame(ExamStatus::Draft, $salinan->status);
        $this->assertSame($this->dosen->id, $salinan->dosen_id);
        $this->assertSame([false, true, 75, 4], [$salinan->acak_soal, $salinan->acak_opsi, $salinan->durasi_menit, $salinan->batas_pelanggaran]);

        $asli = $this->exam->questions()->with('options')->get();
        $baru = $salinan->questions()->with('options')->get();
        $this->assertCount(2, $baru);
        $ringkas = fn ($soal) => $soal->map(fn ($q) => [
            $q->urutan, $q->tipe, $q->teks, $q->bobot, $q->kunci_esai, $q->keywords,
            $q->options->map(fn ($o) => [$o->label, $o->teks, $o->is_correct, $o->posisi_tetap])->all(),
        ])->all();
        $this->assertSame($ringkas($asli), $ringkas($baru));
        $this->assertEmpty(array_intersect($asli->pluck('id')->all(), $baru->pluck('id')->all()), 'Soal baru, bukan dipindah.');

        $this->assertSame($this->exam->kelas()->pluck('classes.id')->all(), $salinan->kelas()->pluck('classes.id')->all());
        $this->assertSame('10.0.0.0/8', $salinan->access->ip_allowlist);
        $this->assertNull($salinan->access->kode_akses, 'Kode akses lama tidak ikut disalin.');
        $this->assertSame(0, $salinan->attempts()->count());
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ujian_diduplikat', 'subjek_id' => $salinan->id]);

        $this->assertSame(2, $this->exam->questions()->count(), 'Ujian asli tidak berubah.');
    }

    public function test_dosen_lain_tidak_bisa_menduplikat(): void
    {
        $this->actingAs(User::factory()->dosen()->create())->post("/dosen/ujian/{$this->exam->id}/duplikat")->assertForbidden();
        $this->assertSame(1, Exam::count());
    }

    public function test_pratinjau_menampilkan_soal_tanpa_kunci_dan_tanpa_membuat_attempt(): void
    {
        $respons = $this->get("/dosen/ujian/{$this->exam->id}/pratinjau")->assertOk()
            ->assertSee('Mode pratinjau')
            ->assertSee('Soal PG satu')->assertSee('Soal esai dua')->assertSee('Semua salah')
            ->assertDontSee('KUNCI-ESAI-RAHASIA')->assertDontSee('kata-rahasia');

        // Urutan soal mengikuti pengaturan (acak soal mati) dan opsi terkunci tetap terakhir.
        $html = $respons->getContent();
        $this->assertLessThan(strpos($html, 'Soal esai dua'), strpos($html, 'Soal PG satu'));
        $this->assertMatchesRegularExpression('/D\.<\/span>\s*<span[^>]*>Semua salah/', $html);

        foreach (['exam_attempts', 'student_answers', 'exam_logs', 'exam_results'] as $tabel) {
            $this->assertDatabaseCount($tabel, 0);
        }
    }

    public function test_pratinjau_bisa_untuk_draf_dan_seed_menentukan_urutan(): void
    {
        $this->exam->update(['status' => ExamStatus::Draft, 'acak_soal' => true]);
        for ($i = 3; $i <= 8; $i++) {
            Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'urutan' => $i, 'teks' => "Soal tambahan {$i}"]);
        }

        $urutan = fn (int $seed) => $this->get("/dosen/ujian/{$this->exam->id}/pratinjau?seed={$seed}")->assertOk()->getContent();
        $this->assertSame($urutan(11), $urutan(11), 'Seed sama, tampilan sama.');
        $this->assertNotSame($urutan(11), $urutan(12));
        $this->get("/dosen/ujian/{$this->exam->id}/pratinjau?seed=abc")->assertOk();
    }

    public function test_pratinjau_hanya_untuk_dosen_pemilik(): void
    {
        $this->actingAs(User::factory()->dosen()->create())->get("/dosen/ujian/{$this->exam->id}/pratinjau")->assertForbidden();
        $this->actingAs(User::factory()->mahasiswa()->create())->get("/dosen/ujian/{$this->exam->id}/pratinjau")->assertForbidden();
    }
}
