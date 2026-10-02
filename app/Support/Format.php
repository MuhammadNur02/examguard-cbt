<?php

namespace App\Support;

class Format
{
    /** Angka gaya Indonesia tanpa nol berlebih: 2 → "2", 2.5 → "2,5", 1234.25 → "1.234,25". */
    public static function angka(float|int|null $nilai, int $desimal = 2): string
    {
        if ($nilai === null) {
            return '–';
        }

        $teks = number_format((float) $nilai, $desimal, ',', '.');

        return str_contains($teks, ',') ? rtrim(rtrim($teks, '0'), ',') : $teks;
    }
}
