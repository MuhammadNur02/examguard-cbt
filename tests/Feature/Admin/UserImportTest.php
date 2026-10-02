<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function unggah(string $isi, string $nama = 'akun.csv')
    {
        return $this->actingAs($this->admin)
            ->from('/admin/pengguna/impor')
            ->post('/admin/pengguna/impor', ['berkas' => UploadedFile::fake()->createWithContent($nama, $isi)]);
    }

    public function test_impor_csv_valid_membuat_akun_dan_menampilkan_kata_sandi_sekali(): void
    {
        $respons = $this->unggah("nim_nidn,nama,peran,kata_sandi\n2301001,Andi Pratama,,\n0601010101,Siti Rahmawati,dosen,sandiAwal123\n");

        $respons->assertOk()->assertSee('2 akun berhasil diimpor');
        $andi = User::firstWhere('nim_nidn', '2301001');
        $siti = User::firstWhere('nim_nidn', '0601010101');
        $this->assertSame(Role::Mahasiswa, $andi->role);
        $this->assertSame(Role::Dosen, $siti->role);
        $this->assertTrue(Hash::check('sandiAwal123', $siti->password));

        // Kata sandi buatan sistem tampil di halaman hasil dan benar.
        preg_match('/<td class="font-mono text-ink">([a-z2-9]{10})<\/td>/', $respons->getContent(), $cocok);
        $this->assertNotEmpty($cocok, 'Kata sandi buatan sistem tidak tampil.');
        $this->assertTrue(Hash::check($cocok[1], $andi->password));
        $this->assertSessionMissingKredensial();

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'akun_diimpor', 'user_id' => $this->admin->id]);
    }

    private function assertSessionMissingKredensial(): void
    {
        $this->assertNull(session('kredensial'));
        $this->assertNull(session('galat_impor'));
    }

    public function test_csv_excel_indonesia_titik_koma_dan_bom_diterima(): void
    {
        $this->unggah("\xEF\xBB\xBFnim_nidn;nama;peran;kata_sandi\r\n2301001;Andi Pratama;Mahasiswa;\r\n")->assertOk();

        $this->assertDatabaseHas('users', ['nim_nidn' => '2301001', 'nama' => 'Andi Pratama', 'role' => 'mahasiswa']);
    }

    public function test_baris_salah_dilaporkan_per_nomor_baris_dan_tidak_ada_yang_disimpan(): void
    {
        User::factory()->create(['nim_nidn' => '2300000']);
        $isi = implode("\n", [
            'nim_nidn,nama,peran,kata_sandi',
            '2301001,Andi,,',          // baris 2 valid
            '2300000,Sudah Ada,,',     // baris 3 sudah terdaftar
            '2301001,Ganda,,',         // baris 4 ganda dengan baris 2
            ',Tanpa NIM,,',            // baris 5
            '2301005,,,',              // baris 6 nama kosong
            '2301006,Peran Salah,admin,', // baris 7 admin tidak boleh diimpor
            '2301007,Sandi Pendek,,abc',  // baris 8
            '23 01 08,Spasi,,',        // baris 9
        ]);

        $respons = $this->unggah($isi);

        $respons->assertRedirect('/admin/pengguna/impor');
        $galat = session('galat_impor');
        $this->assertSame([
            'Baris 3: NIM/NIDN 2300000 sudah terdaftar.',
            'Baris 4: NIM/NIDN 2301001 ganda dengan baris 2.',
            'Baris 5: NIM/NIDN wajib diisi.',
            'Baris 6: nama wajib diisi.',
            'Baris 7: peran harus mahasiswa atau dosen.',
            'Baris 8: kata sandi minimal 8 karakter.',
            'Baris 9: NIM/NIDN hanya boleh huruf, angka, titik, garis bawah, atau tanda hubung (maks. 30 karakter).',
        ], $galat);

        // Baris 2 yang valid pun tidak disimpan.
        $this->assertDatabaseMissing('users', ['nim_nidn' => '2301001']);
        $this->assertSame(2, User::count());

        $this->get('/admin/pengguna/impor')->assertSee('Impor dibatalkan')->assertSee('Baris 3: NIM/NIDN 2300000 sudah terdaftar.');
    }

    public function test_kolom_wajib_hilang_ditolak(): void
    {
        $this->unggah("nim,nama\n2301001,Andi\n");

        $this->assertStringContainsString('Kolom wajib tidak ditemukan: nim_nidn', session('galat_impor')[0]);
        $this->assertSame(1, User::count());
    }

    public function test_berkas_tanpa_data_ditolak(): void
    {
        $this->unggah("nim_nidn,nama\n");

        $this->assertSame(['Berkas tidak berisi baris data.'], session('galat_impor'));
    }

    public function test_melebihi_batas_baris_ditolak(): void
    {
        $baris = ['nim_nidn,nama'];
        for ($i = 1; $i <= 501; $i++) {
            $baris[] = sprintf('29%05d,Mahasiswa %d', $i, $i);
        }

        $this->unggah(implode("\n", $baris));

        $this->assertStringContainsString('Maksimal 500 baris', session('galat_impor')[0]);
        $this->assertSame(1, User::count());
    }

    public function test_berkas_bukan_csv_ditolak(): void
    {
        $this->actingAs($this->admin)->from('/admin/pengguna/impor')
            ->post('/admin/pengguna/impor', ['berkas' => UploadedFile::fake()->image('foto.png')])
            ->assertSessionHasErrors('berkas');
    }

    public function test_akun_hasil_impor_bisa_login(): void
    {
        $this->unggah("nim_nidn,nama,peran,kata_sandi\n2301001,Andi,,sandiAndi123\n")->assertOk();
        $this->post('/logout');

        $this->post('/login', ['nim_nidn' => '2301001', 'password' => 'sandiAndi123'])->assertRedirect('/mahasiswa');
    }

    public function test_templat_bisa_diunduh(): void
    {
        $respons = $this->actingAs($this->admin)->get('/admin/pengguna/impor/templat');

        $respons->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("nim_nidn,nama,peran,kata_sandi\n", $respons->streamedContent());
    }
}
