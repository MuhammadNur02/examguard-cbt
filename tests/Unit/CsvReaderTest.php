<?php

namespace Tests\Unit;

use App\Support\CsvReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CsvReaderTest extends TestCase
{
    private function berkas(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $isi);

        return $path;
    }

    public function test_membaca_csv_koma_dengan_nomor_baris(): void
    {
        $hasil = CsvReader::read($this->berkas("nim_nidn,nama\n2301001,Andi\n2301002,\"Budi, S.\"\n"));

        $this->assertSame(['nim_nidn', 'nama'], $hasil['header']);
        $this->assertSame(2, $hasil['rows'][0]['line']);
        $this->assertSame(['nim_nidn' => '2301001', 'nama' => 'Andi'], $hasil['rows'][0]['data']);
        $this->assertSame('Budi, S.', $hasil['rows'][1]['data']['nama']);
        $this->assertSame(3, $hasil['rows'][1]['line']);
    }

    public function test_titik_koma_dan_bom_excel_dikenali(): void
    {
        $hasil = CsvReader::read($this->berkas("\xEF\xBB\xBFNIM_NIDN;Nama;Peran\r\n2301001;Andi;mahasiswa\r\n"));

        $this->assertSame(['nim_nidn', 'nama', 'peran'], $hasil['header']);
        $this->assertSame(['nim_nidn' => '2301001', 'nama' => 'Andi', 'peran' => 'mahasiswa'], $hasil['rows'][0]['data']);
    }

    public function test_baris_kosong_dilewati_tetapi_nomor_baris_tetap_sesuai_berkas(): void
    {
        $hasil = CsvReader::read($this->berkas("nim_nidn,nama\n\n2301001,Andi\n  \n2301002,Budi"));

        $this->assertCount(2, $hasil['rows']);
        $this->assertSame(3, $hasil['rows'][0]['line']);
        $this->assertSame(5, $hasil['rows'][1]['line']);
    }

    public function test_kolom_kurang_diisi_string_kosong(): void
    {
        $hasil = CsvReader::read($this->berkas("nim_nidn,nama,peran\n2301001,Andi\n"));

        $this->assertSame('', $hasil['rows'][0]['data']['peran']);
    }

    public function test_berkas_kosong_ditolak(): void
    {
        $this->expectException(RuntimeException::class);
        CsvReader::read($this->berkas(''));
    }
}
