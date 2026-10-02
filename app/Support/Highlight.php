<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Sorot kata kunci di jawaban mahasiswa (StyleGuide §7: disorot gold-100).
 * Teks selalu di-escape; hanya elemen <mark> buatan sendiri yang disisipkan.
 */
class Highlight
{
    /** @param  list<string>  $kataKunci */
    public static function kataKunci(string $teks, array $kataKunci): HtmlString
    {
        $kataKunci = array_values(array_filter(array_map('trim', $kataKunci), fn ($k) => $k !== ''));
        if ($kataKunci === []) {
            return new HtmlString(e($teks));
        }

        // Kata kunci terpanjang lebih dulu agar frasa tidak terpotong oleh kata yang lebih pendek.
        usort($kataKunci, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $pola = '/('.implode('|', array_map(fn ($k) => preg_quote($k, '/'), $kataKunci)).')/iu';

        $hasil = '';
        foreach (preg_split($pola, $teks, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $bagian) {
            $hasil .= $i % 2 === 1
                ? '<mark class="rounded-sm bg-gold-100 px-0.5 text-ink">'.e($bagian).'</mark>'
                : e($bagian);
        }

        return new HtmlString($hasil);
    }
}
