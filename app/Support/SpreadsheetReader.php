<?php

namespace App\Support;

use DateTimeInterface;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

/**
 * Pembaca tabel impor: CSV (lewat CsvReader) atau XLSX (lembar pertama).
 * Keluaran sama: judul kolom huruf kecil dan baris dengan nomor baris berkas.
 */
class SpreadsheetReader
{
    public const EKSTENSI = ['csv', 'txt', 'xlsx'];

    /**
     * @return array{header: list<string>, rows: list<array{line: int, data: array<string, string>}>}
     */
    public static function read(string $path, string $ekstensi): array
    {
        return strtolower($ekstensi) === 'xlsx' ? self::xlsx($path) : CsvReader::read($path);
    }

    /**
     * @return array{header: list<string>, rows: list<array{line: int, data: array<string, string>}>}
     */
    private static function xlsx(string $path): array
    {
        // Pertahankan baris kosong agar kunci iterator = nomor baris sebenarnya di lembar.
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        try {
            $reader->open($path);
        } catch (Throwable) {
            throw new RuntimeException('Berkas Excel tidak dapat dibaca. Simpan ulang sebagai .xlsx atau CSV.');
        }

        $header = null;
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $line => $row) {
                    $nilai = array_map(self::teks(...), $row->toArray());
                    if ($header === null) {
                        if (implode('', $nilai) === '') {
                            continue;
                        }
                        $header = array_map(fn (string $k) => strtolower(trim($k)), $nilai);

                        continue;
                    }
                    if (trim(implode('', $nilai)) === '') {
                        continue;
                    }

                    $data = [];
                    foreach ($header as $i => $kolom) {
                        $data[$kolom] = trim($nilai[$i] ?? '');
                    }
                    $rows[] = ['line' => (int) $line, 'data' => $data];
                }
                break; // hanya lembar pertama
            }
        } finally {
            $reader->close();
        }

        if ($header === null) {
            throw new RuntimeException('Berkas kosong atau baris judul tidak ada.');
        }

        return ['header' => $header, 'rows' => $rows];
    }

    /** Nilai sel sebagai teks; bilangan bulat tanpa ".0" (mis. NIM yang disimpan sebagai angka). */
    private static function teks(mixed $nilai): string
    {
        return match (true) {
            $nilai === null => '',
            is_bool($nilai) => $nilai ? '1' : '0',
            is_float($nilai) && floor($nilai) === $nilai && abs($nilai) < 1e15 => (string) (int) $nilai,
            $nilai instanceof DateTimeInterface => $nilai->format('Y-m-d H:i'),
            default => (string) $nilai,
        };
    }
}
