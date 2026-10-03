<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Daftar jaringan yang diizinkan per ujian (FR-02.9): alamat IP tunggal atau
 * CIDR, IPv4/IPv6, satu per baris (koma juga diterima, "#" untuk komentar).
 * IP yang dibaca bergantung pada konfigurasi trusted proxies (docs/KEAMANAN.md).
 */
class JaringanIp
{
    public const MAKS_BARIS = 50;

    public static function valid(string $baris): bool
    {
        [$alamat, $prefix] = array_pad(explode('/', $baris, 2), 2, null);
        if (filter_var($alamat, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($prefix === null) {
            return true;
        }

        $maks = filter_var($alamat, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;

        return ctype_digit($prefix) && (int) $prefix <= $maks;
    }

    /** @return list<string> baris berisi, tanpa komentar dan duplikat */
    public static function pecah(?string $teks): array
    {
        $baris = preg_split('/[\r\n,]+/', (string) $teks);
        $baris = array_map(fn (string $b) => trim(explode('#', $b, 2)[0]), $baris);

        return array_values(array_unique(array_filter($baris, fn (string $b) => $b !== '')));
    }

    /** @param  list<string>  $daftar  kosong berarti semua jaringan diizinkan */
    public static function diizinkan(?string $ip, array $daftar): bool
    {
        return $daftar === [] || ($ip !== null && IpUtils::checkIp($ip, $daftar));
    }
}
