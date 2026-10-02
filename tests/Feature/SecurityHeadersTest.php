<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_keamanan_ada_di_halaman_tamu_dan_terautentikasi(): void
    {
        $responses = [
            $this->get('/login'),
            $this->actingAs(User::factory()->create())->get('/mahasiswa'),
        ];

        foreach ($responses as $response) {
            $response->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'same-origin');
        }
    }

    public function test_cookie_sesi_httponly(): void
    {
        $cookie = collect($this->get('/login')->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }
}
