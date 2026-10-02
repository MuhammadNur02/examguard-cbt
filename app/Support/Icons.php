<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Membaca isi SVG ikon Lucide (lucide-static, lisensi ISC) dari resources/icons.
 */
class Icons
{
    /** @var array<string, string> */
    private static array $cache = [];

    /** Elemen di dalam tag <svg> untuk ikon bernama $name. */
    public static function inner(string $name): string
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $path = resource_path("icons/{$name}.svg");
        if (! preg_match('/^[a-z0-9-]+$/', $name) || ! is_file($path)) {
            throw new InvalidArgumentException("Ikon [{$name}] tidak ditemukan.");
        }

        $svg = (string) file_get_contents($path);
        $svg = preg_replace('/<!--.*?-->/s', '', $svg);
        $inner = preg_replace('/^.*?<svg[^>]*>|<\/svg>.*$/s', '', $svg);

        return self::$cache[$name] = trim((string) $inner);
    }
}
