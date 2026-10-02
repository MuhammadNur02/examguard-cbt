<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $nimNidn, string $password = 'password', string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/login', ['nim_nidn' => $nimNidn, 'password' => $password]);
    }

    public function test_halaman_login_tampil_dengan_token_csrf(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('NIM / NIDN / Username')
            ->assertSee('name="_token"', false);
    }

    public function test_beranda_mengarahkan_tamu_ke_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    /** @return array<string, array{string, string}> */
    public static function peranDanTujuan(): array
    {
        return [
            'mahasiswa dengan NIM' => ['mahasiswa', '/mahasiswa'],
            'dosen dengan NIDN' => ['dosen', '/dosen'],
            'admin dengan username' => ['admin', '/admin'],
        ];
    }

    #[DataProvider('peranDanTujuan')]
    public function test_peran_menentukan_halaman_tujuan(string $peran, string $tujuan): void
    {
        $user = User::factory()->{$peran}()->create();

        $this->login($user->nim_nidn)->assertRedirect($tujuan);
        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertRedirect($tujuan);
    }

    public function test_identitas_dengan_spasi_di_tepi_tetap_dikenali(): void
    {
        $user = User::factory()->create(['nim_nidn' => '2301999']);

        $this->login('  2301999 ')->assertRedirect('/mahasiswa');
        $this->assertAuthenticatedAs($user);
    }

    public function test_kata_sandi_salah_ditolak(): void
    {
        $user = User::factory()->create();

        $this->login($user->nim_nidn, 'salah')
            ->assertSessionHasErrors(['nim_nidn' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_akun_tidak_dikenal_mendapat_pesan_yang_sama(): void
    {
        $this->login('9999999', 'apa-saja')
            ->assertSessionHasErrors(['nim_nidn' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $user = User::factory()->nonaktif()->create();

        $this->login($user->nim_nidn)
            ->assertSessionHasErrors(['nim_nidn' => __('auth.inactive')]);
        $this->assertGuest();
    }

    public function test_input_wajib_divalidasi(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['nim_nidn', 'password']);
        $this->post('/login', ['nim_nidn' => str_repeat('1', 31), 'password' => 'x'])
            ->assertSessionHasErrors('nim_nidn');
        $this->assertGuest();
    }

    public function test_lima_kali_gagal_berturut_turut_memicu_penundaan(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->login($user->nim_nidn, 'salah')->assertSessionHasErrors(['nim_nidn' => __('auth.failed')]);
        }

        // Percobaan ke-6 ditunda walaupun kata sandinya benar.
        $respons = $this->login($user->nim_nidn);
        $respons->assertSessionHasErrors('nim_nidn');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('nim_nidn'));
        $this->assertGuest();

        // Setelah masa penundaan lewat, login kembali bisa.
        $this->travel(61)->seconds();
        $this->login($user->nim_nidn)->assertRedirect('/mahasiswa');
        $this->assertAuthenticatedAs($user);
    }

    public function test_empat_kali_gagal_belum_ditunda(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 4; $i++) {
            $this->login($user->nim_nidn, 'salah');
        }

        $this->login($user->nim_nidn)->assertRedirect('/mahasiswa');
    }

    public function test_login_berhasil_mereset_hitungan_gagal(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 4; $i++) {
            $this->login($user->nim_nidn, 'salah');
        }
        $this->login($user->nim_nidn)->assertRedirect('/mahasiswa');
        $this->post('/logout');

        for ($i = 1; $i <= 4; $i++) {
            $this->login($user->nim_nidn, 'salah');
        }
        $this->login($user->nim_nidn)->assertRedirect('/mahasiswa');
    }

    public function test_penundaan_tidak_mengunci_akun_dari_ip_lain(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->login($user->nim_nidn, 'salah', '10.0.0.66');
        }
        $this->login($user->nim_nidn, 'password', '10.0.0.66')->assertSessionHasErrors('nim_nidn');

        // Pemilik akun dari komputer lain tetap bisa masuk.
        $this->login($user->nim_nidn, 'password', '10.0.0.7')->assertRedirect('/mahasiswa');
        $this->assertAuthenticatedAs($user);
    }

    public function test_sesi_diregenerasi_saat_login(): void
    {
        $user = User::factory()->create();
        $this->get('/login');
        $idSebelum = session()->getId();

        $this->login($user->nim_nidn);

        $this->assertNotSame($idSebelum, session()->getId());
    }

    public function test_pengguna_yang_sudah_login_dialihkan_dari_halaman_login(): void
    {
        $dosen = User::factory()->dosen()->create();

        $this->actingAs($dosen)->get('/login')->assertRedirect('/dosen');
    }

    public function test_logout_mengakhiri_sesi(): void
    {
        $user = User::factory()->create();
        $this->login($user->nim_nidn);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/mahasiswa')->assertRedirect('/login');
    }

    public function test_logout_hanya_lewat_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logout')->assertStatus(405);
        $this->assertAuthenticatedAs($user);
    }

    public function test_permintaan_tanpa_token_csrf_ditolak(): void
    {
        $user = User::factory()->create();
        // Pemeriksaan CSRF dilewati Laravel saat env "testing"; aktifkan untuk tes ini.
        $this->app['env'] = 'local';

        $this->post('/login', ['nim_nidn' => $user->nim_nidn, 'password' => 'password'])
            ->assertStatus(419);
        $this->withHeader('Sec-Fetch-Site', 'cross-site')
            ->post('/login', ['nim_nidn' => $user->nim_nidn, 'password' => 'password'])
            ->assertStatus(419);
        $this->assertGuest();

        // Kontrol: dengan token yang benar, permintaan yang sama berhasil.
        $this->get('/login');
        $this->withHeader('Sec-Fetch-Site', 'cross-site')
            ->post('/login', ['_token' => session()->token(), 'nim_nidn' => $user->nim_nidn, 'password' => 'password'])
            ->assertRedirect('/mahasiswa');
        $this->assertAuthenticatedAs($user);
    }
}
