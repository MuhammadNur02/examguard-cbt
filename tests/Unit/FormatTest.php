<?php

namespace Tests\Unit;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_format_angka_indonesia(): void
    {
        $this->assertSame('2', Format::angka(2));
        $this->assertSame('2', Format::angka(2.0));
        $this->assertSame('2,5', Format::angka(2.5));
        $this->assertSame('7,25', Format::angka(7.25));
        $this->assertSame('1.234,5', Format::angka(1234.5));
        $this->assertSame('0,1', Format::angka(0.1));
        $this->assertSame('10', Format::angka(10));
        $this->assertSame('–', Format::angka(null));
        $this->assertSame('0,857', Format::angka(0.8571, 3));
    }
}
