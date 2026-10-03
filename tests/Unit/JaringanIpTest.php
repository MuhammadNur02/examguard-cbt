<?php

namespace Tests\Unit;

use App\Support\JaringanIp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JaringanIpTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function baris(): array
    {
        return [
            'IPv4 tunggal' => ['10.20.30.40', true],
            'CIDR IPv4' => ['10.20.0.0/16', true],
            'CIDR /0' => ['0.0.0.0/0', true],
            'IPv6' => ['2001:db8::1', true],
            'CIDR IPv6' => ['2001:db8::/32', true],
            'oktet > 255' => ['10.0.0.300', false],
            'prefix > 32' => ['10.0.0.0/33', false],
            'prefix IPv6 > 128' => ['2001:db8::/129', false],
            'prefix bukan angka' => ['10.0.0.0/ab', false],
            'nama host' => ['kampus.ac.id', false],
            'rentang' => ['10.0.0.1-10.0.0.9', false],
        ];
    }

    #[DataProvider('baris')]
    public function test_validasi_alamat_atau_cidr(string $baris, bool $valid): void
    {
        $this->assertSame($valid, JaringanIp::valid($baris));
    }

    public function test_pecah_mengabaikan_baris_kosong_komentar_dan_duplikat(): void
    {
        $this->assertSame(
            ['10.0.0.0/8', '192.168.1.5'],
            JaringanIp::pecah("10.0.0.0/8\r\n\n  192.168.1.5  # lab komputer\n# Wi-Fi tamu tidak diizinkan\n10.0.0.0/8, "),
        );
        $this->assertSame([], JaringanIp::pecah(null));
    }

    public function test_pencocokan_ip(): void
    {
        $daftar = ['10.20.0.0/16', '192.168.1.5', '2001:db8::/32'];

        $this->assertTrue(JaringanIp::diizinkan('10.20.255.1', $daftar));
        $this->assertTrue(JaringanIp::diizinkan('192.168.1.5', $daftar));
        $this->assertTrue(JaringanIp::diizinkan('2001:db8:abcd::7', $daftar));
        $this->assertFalse(JaringanIp::diizinkan('10.21.0.1', $daftar));
        $this->assertFalse(JaringanIp::diizinkan('192.168.1.6', $daftar));
        $this->assertFalse(JaringanIp::diizinkan(null, $daftar));
        $this->assertTrue(JaringanIp::diizinkan('8.8.8.8', []), 'Tanpa daftar berarti semua jaringan diizinkan.');
    }
}
