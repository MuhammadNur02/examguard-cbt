<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Task 1.6 / FR-02.6: admin mengelola kelas dan keanggotaan mahasiswa. */
class ClassManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_membuat_dan_mengubah_kelas(): void
    {
        $this->post('/admin/kelas', ['nama' => 'PTI 2024 B', 'keterangan' => 'Angkatan 2024'])->assertRedirect();
        $kelas = Kelas::sole();
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'kelas_dibuat', 'subjek_id' => $kelas->id]);

        $this->post('/admin/kelas', ['nama' => 'PTI 2024 B'])->assertSessionHasErrors('nama');
        $this->put("/admin/kelas/{$kelas->id}", ['nama' => 'PTI 2024 C'])->assertSessionHas('status');
        $this->assertSame('PTI 2024 C', $kelas->fresh()->nama);

        $this->get('/admin/kelas')->assertOk()->assertSee('PTI 2024 C');
    }

    public function test_tambah_dan_keluarkan_anggota(): void
    {
        $kelas = Kelas::factory()->create();
        $mhs = User::factory()->mahasiswa()->create(['nim_nidn' => '2301001']);
        $dosen = User::factory()->dosen()->create(['nim_nidn' => '0601010101']);

        $this->post("/admin/kelas/{$kelas->id}/anggota", ['nim_nidn' => ' 2301001 '])->assertSessionHas('status');
        $this->post("/admin/kelas/{$kelas->id}/anggota", ['nim_nidn' => '2301001'])->assertSessionHas('status');
        $this->assertSame(1, $kelas->mahasiswa()->count(), 'Menambah dua kali tidak menggandakan anggota.');

        $this->post("/admin/kelas/{$kelas->id}/anggota", ['nim_nidn' => '0601010101'])->assertSessionHasErrors('nim_nidn');
        $this->post("/admin/kelas/{$kelas->id}/anggota", ['nim_nidn' => '9999'])->assertSessionHasErrors('nim_nidn');
        $this->assertFalse($kelas->mahasiswa()->whereKey($dosen->id)->exists());

        $this->get("/admin/kelas/{$kelas->id}")->assertOk()->assertSee('2301001');

        $this->delete("/admin/kelas/{$kelas->id}/anggota/{$mhs->id}")->assertSessionHas('status');
        $this->assertSame(0, $kelas->mahasiswa()->count());
    }

    public function test_impor_anggota_semua_atau_tidak_sama_sekali(): void
    {
        $kelas = Kelas::factory()->create();
        User::factory()->mahasiswa()->create(['nim_nidn' => '2301001']);
        User::factory()->mahasiswa()->create(['nim_nidn' => '2301002']);
        User::factory()->dosen()->create(['nim_nidn' => '0601010101']);

        $salah = UploadedFile::fake()->createWithContent('anggota.csv', "nim_nidn\n2301001\n0601010101\n\n9999\n");
        $this->from("/admin/kelas/{$kelas->id}")->post("/admin/kelas/{$kelas->id}/anggota/impor", ['berkas' => $salah])
            ->assertSessionHas('galat_impor', [
                'Baris 3: NIM 0601010101 tidak terdaftar sebagai mahasiswa.',
                'Baris 5: NIM 9999 tidak terdaftar sebagai mahasiswa.',
            ]);
        $this->assertSame(0, $kelas->mahasiswa()->count());

        $benar = UploadedFile::fake()->createWithContent('anggota.csv', "\xEF\xBB\xBFNIM_NIDN\r\n2301001\r\n2301002\r\n2301001\r\n");
        $this->post("/admin/kelas/{$kelas->id}/anggota/impor", ['berkas' => $benar])
            ->assertSessionHas('status', '2 mahasiswa ditambahkan ke kelas '.$kelas->nama.'.');
        $this->assertSame(2, $kelas->mahasiswa()->count());
    }

    public function test_kelas_yang_dipakai_ujian_tidak_bisa_dihapus(): void
    {
        $dipakai = Kelas::factory()->create();
        Exam::factory()->create()->kelas()->attach($dipakai->id);
        $bebas = Kelas::factory()->create();

        $this->delete("/admin/kelas/{$dipakai->id}")->assertSessionHas('error');
        $this->delete("/admin/kelas/{$bebas->id}")->assertRedirect('/admin/kelas');

        $this->assertModelExists($dipakai);
        $this->assertModelMissing($bebas);
    }
}
