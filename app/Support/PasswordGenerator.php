<?php

namespace App\Support;

/**
 * Kata sandi awal/reset acak tanpa karakter yang mudah tertukar (i, l, o, 0, 1).
 */
class PasswordGenerator
{
    private const ALFABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public static function make(int $panjang = 10): string
    {
        $hasil = '';
        $maks = strlen(self::ALFABET) - 1;
        for ($i = 0; $i < $panjang; $i++) {
            $hasil .= self::ALFABET[random_int(0, $maks)];
        }

        return $hasil;
    }
}
