<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, int}> */
    public static function aksesPerPeran(): array
    {
        return [
            'admin ke area admin' => ['admin', '/admin', 200],
            'admin ke area dosen' => ['admin', '/dosen', 403],
            'admin ke area mahasiswa' => ['admin', '/mahasiswa', 403],
            'dosen ke area dosen' => ['dosen', '/dosen', 200],
            'dosen ke area admin' => ['dosen', '/admin', 403],
            'dosen ke area mahasiswa' => ['dosen', '/mahasiswa', 403],
            'mahasiswa ke area mahasiswa' => ['mahasiswa', '/mahasiswa', 200],
            'mahasiswa ke area admin' => ['mahasiswa', '/admin', 403],
            'mahasiswa ke area dosen' => ['mahasiswa', '/dosen', 403],
        ];
    }

    #[DataProvider('aksesPerPeran')]
    public function test_otorisasi_per_peran(string $peran, string $url, int $status): void
    {
        $user = User::factory()->{$peran}()->create();

        $this->actingAs($user)->get($url)->assertStatus($status);
    }

    public function test_halaman_403_berbahasa_indonesia(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();

        $this->actingAs($mahasiswa)->get('/admin')
            ->assertForbidden()
            ->assertSee('Akses ditolak');
    }

    /** @return array<string, array{string}> */
    public static function areaTerlindungi(): array
    {
        return ['admin' => ['/admin'], 'dosen' => ['/dosen'], 'mahasiswa' => ['/mahasiswa']];
    }

    #[DataProvider('areaTerlindungi')]
    public function test_tamu_diarahkan_ke_login(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    public function test_dashboard_dosen_hanya_menampilkan_ujian_miliknya(): void
    {
        $dosen = User::factory()->dosen()->create();
        $lain = User::factory()->dosen()->create();
        $dosen->exams()->create(['judul' => 'Ujian Milik Saya', 'mata_kuliah' => 'Web', 'mulai' => now(), 'durasi_menit' => 60]);
        $lain->exams()->create(['judul' => 'Ujian Dosen Lain', 'mata_kuliah' => 'Web', 'mulai' => now(), 'durasi_menit' => 60]);

        $this->actingAs($dosen)->get('/dosen')
            ->assertOk()
            ->assertSee('Ujian Milik Saya')
            ->assertDontSee('Ujian Dosen Lain');
    }
}
