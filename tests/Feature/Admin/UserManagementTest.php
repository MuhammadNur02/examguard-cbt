<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_daftar_akun_dengan_filter_peran_status_dan_pencarian(): void
    {
        User::factory()->create(['nim_nidn' => '2301001', 'nama' => 'Andi Pratama']);
        User::factory()->create(['nim_nidn' => '2301002', 'nama' => 'Bunga Lestari', 'aktif' => false]);
        User::factory()->dosen()->create(['nim_nidn' => '0601018801', 'nama' => 'Siti Rahmawati']);

        $this->actingAs($this->admin)->get('/admin/pengguna')
            ->assertOk()->assertSee('2301001')->assertSee('2301002')->assertSee('0601018801');

        $this->get('/admin/pengguna?peran=dosen')
            ->assertSee('0601018801')->assertDontSee('2301001');

        $this->get('/admin/pengguna?status=nonaktif')
            ->assertSee('2301002')->assertDontSee('2301001');

        $this->get('/admin/pengguna?q=bunga')
            ->assertSee('Bunga Lestari')->assertDontSee('Andi Pratama');

        // Filter tidak dikenal diabaikan, bukan galat.
        $this->get('/admin/pengguna?peran=superuser&status=x')->assertOk()->assertSee('2301001');
    }

    public function test_tambah_akun_dengan_kata_sandi_buatan_sistem(): void
    {
        $respons = $this->actingAs($this->admin)->post('/admin/pengguna', [
            'nim_nidn' => '2309999', 'nama' => 'Mahasiswa Baru', 'role' => 'mahasiswa',
        ]);

        $respons->assertRedirect('/admin/pengguna')->assertSessionHas('kredensial');
        $sandi = session('kredensial')['kata_sandi'];
        $user = User::firstWhere('nim_nidn', '2309999');
        $this->assertSame(Role::Mahasiswa, $user->role);
        $this->assertTrue(Hash::check($sandi, $user->password));
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'akun_dibuat', 'subjek_id' => $user->id, 'user_id' => $this->admin->id]);

        // Kredensial tampil sekali di halaman berikutnya.
        $this->get('/admin/pengguna')->assertSee($sandi);
        $this->get('/admin/pengguna')->assertDontSee($sandi);
    }

    public function test_tambah_akun_dengan_kata_sandi_dari_admin(): void
    {
        $this->actingAs($this->admin)->post('/admin/pengguna', [
            'nim_nidn' => '0611111111', 'nama' => 'Dosen Baru', 'role' => 'dosen', 'password' => 'sandiDosen123',
        ])->assertRedirect('/admin/pengguna')->assertSessionMissing('kredensial');

        $this->assertTrue(Hash::check('sandiDosen123', User::firstWhere('nim_nidn', '0611111111')->password));
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function inputTidakValid(): array
    {
        return [
            'nim kosong' => [['nim_nidn' => '', 'nama' => 'X', 'role' => 'mahasiswa'], 'nim_nidn'],
            'nim berspasi' => [['nim_nidn' => '23 01', 'nama' => 'X', 'role' => 'mahasiswa'], 'nim_nidn'],
            'nim sudah ada' => [['nim_nidn' => 'admin-ada', 'nama' => 'X', 'role' => 'mahasiswa'], 'nim_nidn'],
            'nama kosong' => [['nim_nidn' => '2300001', 'nama' => '', 'role' => 'mahasiswa'], 'nama'],
            'peran asing' => [['nim_nidn' => '2300001', 'nama' => 'X', 'role' => 'root'], 'role'],
            'sandi pendek' => [['nim_nidn' => '2300001', 'nama' => 'X', 'role' => 'mahasiswa', 'password' => 'pendek'], 'password'],
        ];
    }

    #[DataProvider('inputTidakValid')]
    public function test_validasi_tambah_akun(array $input, string $kolom): void
    {
        User::factory()->create(['nim_nidn' => 'admin-ada']);
        $jumlah = User::count();

        $this->actingAs($this->admin)->post('/admin/pengguna', $input)->assertSessionHasErrors($kolom);
        $this->assertSame($jumlah, User::count());
    }

    public function test_reset_kata_sandi_mengganti_sandi_dan_mengakhiri_sesi(): void
    {
        $mhs = User::factory()->create(['session_token' => 'token-aktif']);
        $hashLama = $mhs->password;

        $this->actingAs($this->admin)->from('/admin/pengguna')
            ->post("/admin/pengguna/{$mhs->id}/reset-password")
            ->assertRedirect('/admin/pengguna')
            ->assertSessionHas('kredensial');

        $mhs->refresh();
        $this->assertNotSame($hashLama, $mhs->password);
        $this->assertTrue(Hash::check(session('kredensial')['kata_sandi'], $mhs->password));
        $this->assertFalse(Hash::check('password', $mhs->password));
        $this->assertNull($mhs->session_token);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'reset_password', 'subjek_id' => $mhs->id]);
    }

    public function test_reset_sesi_mengakhiri_sesi_pengguna_lain(): void
    {
        $mhs = User::factory()->create(['session_token' => 'token-aktif']);

        $this->actingAs($this->admin)->post("/admin/pengguna/{$mhs->id}/reset-sesi")->assertSessionHas('status');

        $this->assertNull($mhs->fresh()->session_token);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'reset_sesi', 'subjek_id' => $mhs->id]);
    }

    public function test_admin_tidak_bisa_reset_sesi_sendiri_lewat_menu(): void
    {
        $this->actingAs($this->admin)->post("/admin/pengguna/{$this->admin->id}/reset-sesi")->assertSessionHas('error');

        $this->assertNotNull($this->admin->fresh()->session_token);
    }

    public function test_nonaktifkan_lalu_aktifkan_kembali(): void
    {
        $mhs = User::factory()->create();

        $this->actingAs($this->admin)->patch("/admin/pengguna/{$mhs->id}/status", ['aktif' => '0'])->assertSessionHas('status');
        $this->assertFalse($mhs->fresh()->aktif);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'akun_dinonaktifkan', 'subjek_id' => $mhs->id]);

        // Mengirim ulang status yang sama tidak menambah catatan audit.
        $this->patch("/admin/pengguna/{$mhs->id}/status", ['aktif' => '0']);
        $this->assertSame(1, AuditLog::where('aksi', 'akun_dinonaktifkan')->count());

        $this->patch("/admin/pengguna/{$mhs->id}/status", ['aktif' => '1']);
        $this->assertTrue($mhs->fresh()->aktif);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'akun_diaktifkan', 'subjek_id' => $mhs->id]);
    }

    public function test_admin_tidak_bisa_menonaktifkan_diri_sendiri(): void
    {
        $this->actingAs($this->admin)->patch("/admin/pengguna/{$this->admin->id}/status", ['aktif' => '0'])
            ->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->aktif);
    }

    /** @return array<string, array{string, string}> */
    public static function aksiAdmin(): array
    {
        return [
            'daftar' => ['get', '/admin/pengguna'],
            'form tambah' => ['get', '/admin/pengguna/tambah'],
            'simpan' => ['post', '/admin/pengguna'],
            'form impor' => ['get', '/admin/pengguna/impor'],
            'impor' => ['post', '/admin/pengguna/impor'],
            'templat' => ['get', '/admin/pengguna/impor/templat'],
            'reset sandi' => ['post', '/admin/pengguna/{id}/reset-password'],
            'reset sesi' => ['post', '/admin/pengguna/{id}/reset-sesi'],
            'status' => ['patch', '/admin/pengguna/{id}/status'],
        ];
    }

    #[DataProvider('aksiAdmin')]
    public function test_dosen_dan_mahasiswa_tidak_bisa_mengelola_akun(string $metode, string $url): void
    {
        $korban = User::factory()->create();
        $url = str_replace('{id}', (string) $korban->id, $url);

        foreach ([User::factory()->dosen()->create(), User::factory()->mahasiswa()->create()] as $pelaku) {
            $this->actingAs($pelaku)->{$metode}($url, ['aktif' => '0'])->assertForbidden();
        }

        $this->assertTrue($korban->fresh()->aktif);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
