<?php

namespace Tests\Feature\Dosen;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Services\QuestionImporter;
use App\Support\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Unit\SpreadsheetReaderTest;

/** Task 2.3 / FR-02.4: impor soal CSV/Excel; baris salah dilaporkan sebelum disimpan. */
class QuestionImportTest extends TestCase
{
    use RefreshDatabase;

    private const JUDUL = 'tipe,teks,bobot,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,kunci,opsi_tetap,kunci_esai,kata_kunci';

    private User $dosen;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosen = User::factory()->dosen()->create();
        $this->exam = Exam::factory()->draft()->create(['dosen_id' => $this->dosen->id]);
        $this->actingAs($this->dosen);
    }

    private function unggah(UploadedFile $berkas)
    {
        return $this->from("/dosen/ujian/{$this->exam->id}/soal/impor")
            ->post("/dosen/ujian/{$this->exam->id}/soal/impor", ['berkas' => $berkas]);
    }

    private function csv(string ...$baris): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('soal.csv', implode("\n", [self::JUDUL, ...$baris])."\n");
    }

    public function test_csv_valid_disimpan_berurutan_setelah_soal_yang_ada(): void
    {
        Question::factory()->pg()->create(['exam_id' => $this->exam->id, 'urutan' => 4]);

        $this->unggah($this->csv(
            'pg,Ibu kota Indonesia?,"2,5",Bandung,Jakarta,Surabaya,Semua salah,,b,D,,',
            'esai,Jelaskan fungsi middleware.,,,,,,,,,Middleware menyaring permintaan HTTP.,"penyaring, permintaan, HTTP"',
        ))->assertRedirect("/dosen/ujian/{$this->exam->id}")->assertSessionHas('status', '2 soal berhasil diimpor.');

        $pg = Question::where('exam_id', $this->exam->id)->where('urutan', 5)->sole();
        $this->assertSame(QuestionType::Pg, $pg->tipe);
        $this->assertSame(2.5, $pg->bobot);
        $this->assertSame(['A', 'B', 'C', 'D'], $pg->options->pluck('label')->all());
        $this->assertSame(['B'], $pg->options->where('is_correct', true)->pluck('label')->values()->all());
        $this->assertSame(['D'], $pg->options->where('posisi_tetap', true)->pluck('label')->values()->all());
        $this->assertNull($pg->kunci_esai);

        $esai = Question::where('exam_id', $this->exam->id)->where('urutan', 6)->sole();
        $this->assertSame(QuestionType::Esai, $esai->tipe);
        $this->assertSame(10.0, $esai->bobot, 'Bobot esai kosong memakai bawaan 10.');
        $this->assertSame(['penyaring', 'permintaan', 'HTTP'], $esai->keywords);
        $this->assertSame(0, $esai->options()->count());

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'soal_diimpor', 'subjek_id' => $this->exam->id]);
    }

    public function test_baris_salah_dilaporkan_dengan_nomor_baris_dan_tidak_ada_yang_disimpan(): void
    {
        $this->unggah($this->csv(
            'pg,Soal benar,1,A1,B1,,,,A,,,',
            'pg,,1,A1,B1,,,,A,,,',
            'pg,Kunci di luar opsi,1,A1,B1,,,,C,,,',
            'pg,Opsi melompat,1,A1,B1,,D1,,A,,,',
            'pg,Bobot salah,200,A1,B1,,,,A,,,',
            'isian,Tipe salah,1,,,,,,,,,',
            'esai,Esai tanpa kunci,10,,,,,,,,,',
            'pg,Tipe tertukar,1,A1,B1,,,,A,,Kunci esai,',
            'pg,Posisi tetap salah,1,A1,B1,,,,A,E,,',
        ))->assertRedirect("/dosen/ujian/{$this->exam->id}/soal/impor")
            ->assertSessionHas('galat_impor', [
                'Baris 3: teks soal wajib diisi.',
                'Baris 4: kunci harus salah satu opsi yang terisi (A–B).',
                'Baris 5: opsi harus diisi berurutan mulai dari A tanpa ada yang terlewat.',
                'Baris 6: bobot harus angka 0,5–100.',
                'Baris 7: tipe harus pg atau esai.',
                'Baris 8: kunci_esai wajib diisi untuk soal esai.',
                'Baris 9: kolom kunci_esai/kata_kunci hanya untuk soal esai; periksa kolom tipe.',
                'Baris 10: opsi_tetap harus label opsi yang terisi (A–B).',
            ]);

        $this->assertSame(0, Question::count());
    }

    public function test_excel_valid_disimpan_dan_galat_excel_memakai_nomor_baris_lembar(): void
    {
        $judul = explode(',', self::JUDUL);
        $valid = SpreadsheetReaderTest::buatXlsx([
            $judul,
            ['pg', 'Hasil 2 + 2?', 1, 3, 4, 5, '', '', 'B', '', '', ''],
            ['esai', "Sebutkan dua\nprotokol transport.", 5, '', '', '', '', '', '', '', 'TCP dan UDP', "TCP\nUDP"],
        ]);
        $this->unggah(new UploadedFile($valid, 'soal.xlsx', null, null, true))->assertSessionHas('status');

        $pg = Question::where('tipe', 'pg')->sole();
        $this->assertSame(['3', '4', '5'], $pg->options->pluck('teks')->all(), 'Angka bulat dari Excel tanpa ".0".');
        $this->assertSame(['TCP', 'UDP'], Question::where('tipe', 'esai')->sole()->keywords);

        $salah = SpreadsheetReaderTest::buatXlsx([$judul, ['pg', 'Benar', 1, 'x', 'y', '', '', '', 'A', '', '', ''], [], ['pg', '', 1, 'x', 'y', '', '', '', 'A', '', '', '']]);
        $this->unggah(new UploadedFile($salah, 'soal.xlsx', null, null, true))
            ->assertSessionHas('galat_impor', ['Baris 4: teks soal wajib diisi.']);
        $this->assertSame(2, Question::count());
    }

    public function test_kolom_tidak_dikenal_dan_kolom_wajib_ditolak(): void
    {
        $this->unggah(UploadedFile::fake()->createWithContent('soal.csv', "tipe,teks,kunci jawaban\npg,Soal,A\n"))
            ->assertSessionHas('galat_impor', ['Kolom tidak dikenal: kunci jawaban. Gunakan judul kolom dari templat.']);

        $this->unggah(UploadedFile::fake()->createWithContent('soal.csv', "teks\nSoal\n"))
            ->assertSessionHas('galat_impor', ['Kolom wajib tidak ditemukan: tipe. Gunakan templat.']);

        $this->assertSame(0, Question::count());
    }

    public function test_berkas_selain_csv_atau_excel_ditolak(): void
    {
        $this->unggah(UploadedFile::fake()->create('soal.pdf', 10, 'application/pdf'))->assertSessionHasErrors('berkas');
    }

    public function test_jumlah_baris_dibatasi(): void
    {
        $baris = array_fill(0, QuestionImporter::MAKS_BARIS + 1, 'pg,Soal,1,A1,B1,,,,A,,,');

        $this->unggah($this->csv(...$baris))
            ->assertSessionHas('galat_impor', ['Maksimal '.QuestionImporter::MAKS_BARIS.' soal per berkas; pecah berkas menjadi beberapa bagian.']);
    }

    public function test_ujian_terbit_tidak_bisa_diimpori(): void
    {
        $this->exam->update(['status' => 'published']);

        $this->get("/dosen/ujian/{$this->exam->id}/soal/impor")->assertRedirect("/dosen/ujian/{$this->exam->id}");
        $this->unggah($this->csv('pg,Soal,1,A1,B1,,,,A,,,'))->assertSessionHas('error');
        $this->assertSame(0, Question::count());
    }

    public function test_halaman_impor_dan_templat_lolos_validasi_sendiri(): void
    {
        $this->get("/dosen/ujian/{$this->exam->id}/soal/impor")->assertOk()->assertSee('Unduh templat Excel');

        $importer = app(QuestionImporter::class);

        $csv = $this->get('/dosen/soal/templat?format=csv')->assertOk()->assertDownload('templat-impor-soal.csv');
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $csv->streamedContent());
        $this->assertSame(explode(',', self::JUDUL), SpreadsheetReader::read($path, 'csv')['header']);
        $this->assertSame([], $importer->validasi($path, 'csv', $this->exam)['galat']);

        $xlsx = $this->get('/dosen/soal/templat')->assertOk()->assertDownload('templat-impor-soal.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $xlsx->streamedContent());
        $hasil = $importer->validasi($path, 'xlsx', $this->exam);
        $this->assertSame([], $hasil['galat']);
        $this->assertCount(3, $hasil['soal']);
    }
}
