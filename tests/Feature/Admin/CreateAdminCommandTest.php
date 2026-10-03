<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_admin_dengan_kata_sandi_tersembunyi(): void
    {
        $this->artisan('examguard:buat-admin', ['username' => 'admin.prodi', 'nama' => 'Admin Prodi'])
            ->expectsQuestion('Kata sandi (minimal 10 karakter)', 'sandiKuat2026')
            ->expectsQuestion('Ulangi kata sandi', 'sandiKuat2026')
            ->expectsOutput('Admin admin.prodi dibuat.')
            ->assertSuccessful();

        $admin = User::firstWhere('nim_nidn', 'admin.prodi');
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue(Hash::check('sandiKuat2026', $admin->password));
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'akun_dibuat', 'subjek_id' => $admin->id]);
    }

    public function test_menolak_sandi_pendek_tidak_cocok_dan_username_terpakai(): void
    {
        User::factory()->admin()->create(['nim_nidn' => 'admin']);

        $this->artisan('examguard:buat-admin', ['username' => 'baru', 'nama' => 'X'])
            ->expectsQuestion('Kata sandi (minimal 10 karakter)', 'pendek')
            ->expectsQuestion('Ulangi kata sandi', 'pendek')
            ->assertFailed();

        $this->artisan('examguard:buat-admin', ['username' => 'baru', 'nama' => 'X'])
            ->expectsQuestion('Kata sandi (minimal 10 karakter)', 'sandiKuat2026')
            ->expectsQuestion('Ulangi kata sandi', 'sandiBeda2026')
            ->assertFailed();

        $this->artisan('examguard:buat-admin', ['username' => 'admin', 'nama' => 'X'])
            ->expectsQuestion('Kata sandi (minimal 10 karakter)', 'sandiKuat2026')
            ->expectsQuestion('Ulangi kata sandi', 'sandiKuat2026')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }
}
