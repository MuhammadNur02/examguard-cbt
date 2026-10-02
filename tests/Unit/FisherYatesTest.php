<?php

namespace Tests\Unit;

use App\Support\FisherYates;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class FisherYatesTest extends TestCase
{
    private function rng(int $seed): Randomizer
    {
        return new Randomizer(new Mt19937($seed));
    }

    public function test_seed_sama_menghasilkan_urutan_sama(): void
    {
        $data = range(1, 20);

        $this->assertSame(
            FisherYates::shuffle($data, $this->rng(424242)),
            FisherYates::shuffle($data, $this->rng(424242)),
        );
    }

    public function test_seed_berbeda_menghasilkan_urutan_berbeda(): void
    {
        $data = range(1, 20);

        $this->assertNotSame(
            FisherYates::shuffle($data, $this->rng(1)),
            FisherYates::shuffle($data, $this->rng(2)),
        );
    }

    public function test_hasil_adalah_permutasi_dari_masukan(): void
    {
        $data = ['a', 'b', 'c', 'd', 'e', 'f', 'g'];

        for ($seed = 1; $seed <= 50; $seed++) {
            $hasil = FisherYates::shuffle($data, $this->rng($seed));
            $this->assertCount(7, $hasil);
            $this->assertEqualsCanonicalizing($data, $hasil);
            $this->assertSame(range(0, 6), array_keys($hasil));
        }
    }

    public function test_masukan_kosong_dan_tunggal(): void
    {
        $this->assertSame([], FisherYates::shuffle([], $this->rng(1)));
        $this->assertSame([9], FisherYates::shuffle([9], $this->rng(1)));
    }

    /**
     * Uji keseragaman: untuk 3 elemen ada 6 permutasi, masing-masing harus
     * muncul mendekati 1/6. Dengan 12.000 seed, simpangan > 12% hampir mustahil
     * bila algoritma seragam (sekitar 7 simpangan baku).
     */
    public function test_sebaran_permutasi_seragam(): void
    {
        $hitung = [];
        $percobaan = 12000;
        for ($seed = 1; $seed <= $percobaan; $seed++) {
            $kunci = implode('', FisherYates::shuffle(['a', 'b', 'c'], $this->rng($seed)));
            $hitung[$kunci] = ($hitung[$kunci] ?? 0) + 1;
        }

        $this->assertCount(6, $hitung);
        foreach ($hitung as $permutasi => $jumlah) {
            $this->assertEqualsWithDelta($percobaan / 6, $jumlah, 0.12 * $percobaan / 6, "Permutasi {$permutasi} muncul {$jumlah} kali.");
        }
    }

    public function test_elemen_terkunci_tidak_berpindah(): void
    {
        $opsi = ['A', 'B', 'C', 'D', 'E'];
        $terkunci = [false, false, false, false, true]; // E = "Semua benar"

        $pernahBerubah = false;
        for ($seed = 1; $seed <= 100; $seed++) {
            $hasil = FisherYates::shuffleExceptLocked($opsi, $terkunci, $this->rng($seed));
            $this->assertSame('E', $hasil[4]);
            $this->assertEqualsCanonicalizing($opsi, $hasil);
            $pernahBerubah = $pernahBerubah || $hasil !== $opsi;
        }
        $this->assertTrue($pernahBerubah, 'Elemen yang tidak terkunci seharusnya teracak.');
    }

    public function test_elemen_terkunci_di_tengah(): void
    {
        $hasil = FisherYates::shuffleExceptLocked([1, 2, 3, 4], [false, true, false, true], $this->rng(7));

        $this->assertSame(2, $hasil[1]);
        $this->assertSame(4, $hasil[3]);
        $this->assertEqualsCanonicalizing([1, 3], [$hasil[0], $hasil[2]]);
    }
}
