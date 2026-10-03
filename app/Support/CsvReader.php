<?php

namespace App\Support;

use RuntimeException;
use SplFileObject;

/**
 * Pembaca CSV sederhana untuk impor. Mendeteksi pemisah koma atau titik koma
 * (Excel berlokal Indonesia menyimpan CSV dengan titik koma) dan membuang BOM UTF-8.
 * Sel berkutip boleh berisi baris baru; nomor baris = nomor rekaman, sama dengan
 * nomor baris yang terlihat saat berkas dibuka di Excel.
 */
class CsvReader
{
    /**
     * @return array{header: list<string>, rows: list<array{line: int, data: array<string, string>}>}
     */
    public static function read(string $path): array
    {
        $file = new SplFileObject($path, 'r');
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', (string) $file->fgets());

        if (trim((string) $firstLine) === '') {
            throw new RuntimeException('Berkas kosong atau baris judul tidak ada.');
        }

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $header = array_map(
            fn (string $kolom) => strtolower(trim($kolom)),
            str_getcsv(rtrim($firstLine, "\r\n"), $delimiter, '"', ''),
        );

        $rows = [];
        $line = 1;
        while (($values = $file->fgetcsv($delimiter, '"', '')) !== false) {
            $line++;
            if (trim(implode('', array_map('strval', $values))) === '') {
                continue;
            }

            $data = [];
            foreach ($header as $i => $kolom) {
                $data[$kolom] = trim((string) ($values[$i] ?? ''));
            }
            $rows[] = ['line' => $line, 'data' => $data];
        }

        return ['header' => $header, 'rows' => $rows];
    }
}
