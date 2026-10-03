<?php

namespace App\Support;

/**
 * Kenali jenis perangkat dari user-agent. Hanya heuristik: user-agent dapat
 * diubah pengguna (mis. "mode desktop" di peramban ponsel), sehingga dipakai
 * untuk mencegah ketidaksengajaan, bukan sebagai pengamanan.
 */
class Perangkat
{
    private const POLA_SELULER = '/Mobi|Android|iPhone|iPad|iPod|Windows Phone|IEMobile|Opera Mini|Kindle|Silk\//i';

    public static function seluler(?string $userAgent): bool
    {
        return $userAgent !== null && $userAgent !== '' && preg_match(self::POLA_SELULER, $userAgent) === 1;
    }

    /** Ringkasan yang mudah dibaca dosen, mis. "Chrome · Windows". */
    public static function ringkas(?string $userAgent): string
    {
        if ($userAgent === null || $userAgent === '') {
            return 'Tidak dikenal';
        }

        $peramban = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') && str_contains($userAgent, 'Version/') => 'Safari',
            default => 'Peramban lain',
        };

        $sistem = match (true) {
            str_contains($userAgent, 'iPhone') => 'iOS',
            str_contains($userAgent, 'iPad') => 'iPadOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'CrOS') => 'ChromeOS',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'sistem lain',
        };

        return "{$peramban} · {$sistem}";
    }
}
