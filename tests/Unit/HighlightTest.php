<?php

namespace Tests\Unit;

use App\Support\Highlight;
use PHPUnit\Framework\TestCase;

class HighlightTest extends TestCase
{
    private const MARK = '<mark class="rounded-sm bg-gold-100 px-0.5 text-ink">';

    public function test_kata_kunci_disorot_tanpa_peka_huruf_besar(): void
    {
        $html = (string) Highlight::kataKunci('Data dikirim lewat URL dan url lain.', ['url']);

        $this->assertSame('Data dikirim lewat '.self::MARK.'URL</mark> dan '.self::MARK.'url</mark> lain.', $html);
    }

    public function test_frasa_terpanjang_didahulukan(): void
    {
        $html = (string) Highlight::kataKunci('badan permintaan HTTP', ['permintaan', 'badan permintaan']);

        $this->assertSame(self::MARK.'badan permintaan</mark> HTTP', $html);
    }

    public function test_teks_jawaban_tetap_di_escape(): void
    {
        $html = (string) Highlight::kataKunci('<script>alert("x")</script> data', ['data', '<b>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString(self::MARK.'data</mark>', $html);
    }

    public function test_tanpa_kata_kunci_hanya_escape(): void
    {
        $this->assertSame('a &amp; b', (string) Highlight::kataKunci('a & b', []));
        $this->assertSame('a &amp; b', (string) Highlight::kataKunci('a & b', ['  ']));
    }

    public function test_karakter_khusus_regex_aman(): void
    {
        $html = (string) Highlight::kataKunci('nilai (a+b)*c', ['(a+b)*c']);

        $this->assertSame('nilai '.self::MARK.'(a+b)*c</mark>', $html);
    }
}
