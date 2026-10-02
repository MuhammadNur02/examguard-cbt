<?php

namespace App\Support;

use Random\Randomizer;

/**
 * Pengacakan Fisher-Yates (varian Durstenfeld) dengan PRNG berseed (PRD §9.2).
 *
 * Untuk i = n−1 turun sampai 1: ambil j acak seragam di [0, i], lalu tukar
 * elemen ke-i dan ke-j. Setiap permutasi berpeluang sama (1/n!). Dengan
 * Randomizer(Mt19937(seed)) hasilnya deterministik untuk seed yang sama,
 * sehingga urutan dapat direkonstruksi dari shuffle_seed.
 */
class FisherYates
{
    /**
     * @template T
     *
     * @param  array<T>  $items
     * @return list<T>
     */
    public static function shuffle(array $items, Randomizer $rng): array
    {
        $items = array_values($items);

        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $rng->getInt(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    /**
     * Acak hanya elemen yang tidak terkunci; elemen terkunci tetap di indeksnya
     * (opsi berposisi tetap seperti "Semua benar", FR-03.5).
     *
     * @template T
     *
     * @param  array<T>  $items
     * @param  array<bool>  $terkunci  sejajar dengan $items
     * @return list<T>
     */
    public static function shuffleExceptLocked(array $items, array $terkunci, Randomizer $rng): array
    {
        $items = array_values($items);
        $terkunci = array_values($terkunci);
        $posisiBebas = array_keys(array_filter($terkunci, fn ($kunci) => ! $kunci));

        $teracak = self::shuffle(array_map(fn ($i) => $items[$i], $posisiBebas), $rng);
        foreach ($posisiBebas as $k => $posisi) {
            $items[$posisi] = $teracak[$k];
        }

        return $items;
    }
}
