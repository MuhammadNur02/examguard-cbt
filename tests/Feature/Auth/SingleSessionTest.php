<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureSingleSession;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SingleSessionTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $userAgent = 'Peramban-A')
    {
        return $this->withHeader('User-Agent', $userAgent)
            ->post('/login', ['nim_nidn' => $user->nim_nidn, 'password' => 'password']);
    }

    /** Pindah "perangkat": buang sesi dan pengguna yang sedang aktif di klien uji. */
    private function gantiPerangkat(): void
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
    }

    public function test_login_kedua_membatalkan_sesi_pertama_dan_tercatat(): void
    {
        $user = User::factory()->create();

        // Perangkat A login.
        $this->login($user, 'Peramban-A')->assertRedirect('/mahasiswa');
        $tokenA = session(EnsureSingleSession::SESSION_KEY);
        $this->assertSame($tokenA, $user->fresh()->session_token);

        // Perangkat B login dengan akun yang sama.
        $this->gantiPerangkat();
        $this->login($user, 'Peramban-B')->assertRedirect('/mahasiswa');
        $tokenB = session(EnsureSingleSession::SESSION_KEY);
        $this->assertNotSame($tokenA, $tokenB);
        $this->assertSame($tokenB, $user->fresh()->session_token);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'sesi_diganti', 'user_id' => $user->id, 'subjek_id' => $user->id]);

        // Perangkat B tetap bekerja.
        $this->get('/mahasiswa')->assertOk();

        // Perangkat A kembali memakai sesi lamanya: dikeluarkan dan tercatat.
        $this->gantiPerangkat();
        $this->withSession([Auth::guard('web')->getName() => $user->id, EnsureSingleSession::SESSION_KEY => $tokenA])
            ->withHeader('User-Agent', 'Peramban-A')
            ->get('/mahasiswa')
            ->assertRedirect('/login')
            ->assertSessionHas('status', __('auth.session_replaced'));
        $this->assertGuest();

        $log = AuditLog::where('aksi', 'sesi_lama_ditolak')->sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Peramban-A', $log->detail['user_agent']);

        // Token milik perangkat B tidak terganggu oleh penolakan sesi A.
        $this->assertSame($tokenB, $user->fresh()->session_token);
    }

    public function test_login_pertama_tidak_dicatat_sebagai_penggantian(): void
    {
        $user = User::factory()->create(['session_token' => null]);

        $this->login($user)->assertRedirect('/mahasiswa');

        $this->assertDatabaseMissing('audit_logs', ['aksi' => 'sesi_diganti']);
        $this->assertSame(64, strlen($user->fresh()->session_token));
    }

    public function test_sesi_terautentikasi_tanpa_token_ditolak(): void
    {
        $user = User::factory()->create();

        $this->withSession([Auth::guard('web')->getName() => $user->id])
            ->get('/mahasiswa')
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_permintaan_json_dari_sesi_lama_mendapat_401(): void
    {
        $user = User::factory()->create(['session_token' => 'token-perangkat-baru']);

        $this->withSession([Auth::guard('web')->getName() => $user->id, EnsureSingleSession::SESSION_KEY => 'token-lama'])
            ->getJson('/mahasiswa')
            ->assertUnauthorized()
            ->assertJson(['kode' => 'sesi_berakhir', 'message' => __('auth.session_replaced')]);
    }

    public function test_akun_yang_dinonaktifkan_langsung_dikeluarkan(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/mahasiswa')->assertOk();

        $user->update(['aktif' => false]);

        $this->get('/mahasiswa')
            ->assertRedirect('/login')
            ->assertSessionHas('status', __('auth.inactive'));
        $this->assertGuest();
        $this->assertDatabaseMissing('audit_logs', ['aksi' => 'sesi_lama_ditolak']);
    }

    public function test_logout_menghapus_token_milik_sesi_ini(): void
    {
        $user = User::factory()->create();
        $this->login($user);

        $this->post('/logout');

        $this->assertNull($user->fresh()->session_token);
    }

    public function test_login_kembali_setelah_logout_tidak_dicatat_sebagai_penggantian(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $this->post('/logout');

        $this->login($user)->assertRedirect('/mahasiswa');

        $this->assertDatabaseMissing('audit_logs', ['aksi' => 'sesi_diganti']);
    }
}
