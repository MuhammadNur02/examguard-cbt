<?php

namespace Tests\Unit;

use App\Support\PasswordGenerator;
use PHPUnit\Framework\TestCase;

class PasswordGeneratorTest extends TestCase
{
    public function test_panjang_dan_alfabet_tanpa_karakter_ambigu(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $sandi = PasswordGenerator::make();
            $this->assertSame(10, strlen($sandi));
            $this->assertMatchesRegularExpression('/^[a-hj-km-np-z2-9]+$/', $sandi);
        }
    }

    public function test_hasil_bervariasi(): void
    {
        $hasil = array_map(fn () => PasswordGenerator::make(), range(1, 50));

        $this->assertCount(50, array_unique($hasil));
    }
}
