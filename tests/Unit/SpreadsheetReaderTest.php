<?php

namespace Tests\Unit;

use App\Support\SpreadsheetReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SpreadsheetReaderTest extends TestCase
{
    /** @param list<list<mixed>> $baris */
    public static function buatXlsx(array $baris): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($baris as $nilai) {
            $writer->addRow(Row::fromValues($nilai));
        }
        $writer->close();

        return $path;
    }

    public function test_membaca_xlsx_dengan_nomor_baris_dan_angka_bulat(): void
    {
        $path = self::buatXlsx([
            ['NIM_NIDN', 'Nama', 'Bobot'],
            [2301001, 'Andi', 2.5],
            ['', '', ''],
            ['2301002', 'Budi', 3],
        ]);

        $hasil = SpreadsheetReader::read($path, 'xlsx');

        $this->assertSame(['nim_nidn', 'nama', 'bobot'], $hasil['header']);
        $this->assertSame(['line' => 2, 'data' => ['nim_nidn' => '2301001', 'nama' => 'Andi', 'bobot' => '2.5']], $hasil['rows'][0]);
        $this->assertSame(4, $hasil['rows'][1]['line']);
        $this->assertSame('3', $hasil['rows'][1]['data']['bobot']);
    }

    public function test_csv_tetap_lewat_pembaca_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "a;b\n1;2\n");

        $this->assertSame([['line' => 2, 'data' => ['a' => '1', 'b' => '2']]], SpreadsheetReader::read($path, 'csv')['rows']);
    }

    public function test_berkas_bukan_xlsx_ditolak_dengan_pesan_jelas(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, 'bukan berkas excel');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Berkas Excel tidak dapat dibaca');
        SpreadsheetReader::read($path, 'xlsx');
    }
}
