<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Http\Requests\Dosen\QuestionRequest;
use App\Models\Exam;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor soal dari CSV/XLSX (FR-02.4). Aturan sama dengan form soal
 * (QuestionRequest). Seluruh berkas divalidasi lebih dulu; bila ada satu baris
 * salah, tidak ada yang disimpan dan galat dilaporkan per nomor baris.
 */
class QuestionImporter
{
    public const MAKS_BARIS = 200;

    public const KOLOM = ['tipe', 'teks', 'bobot', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci', 'opsi_tetap', 'kunci_esai', 'kata_kunci'];

    private const MAKS_URUTAN = 999;

    /**
     * @return array{soal: list<array{tipe: QuestionType, teks: string, bobot: float, opsi: array<string, string>, kunci: ?string, tetap: list<string>, kunci_esai: ?string, keywords: ?list<string>}>, galat: list<string>}
     */
    public function validasi(string $path, string $ekstensi, Exam $exam): array
    {
        try {
            $tabel = SpreadsheetReader::read($path, $ekstensi);
        } catch (RuntimeException $e) {
            return $this->gagal($e->getMessage());
        }

        $tidakDikenal = array_diff(array_filter($tabel['header'], fn ($k) => $k !== ''), self::KOLOM);
        if ($tidakDikenal !== []) {
            return $this->gagal('Kolom tidak dikenal: '.implode(', ', $tidakDikenal).'. Gunakan judul kolom dari templat.');
        }
        $kurang = array_diff(['tipe', 'teks'], $tabel['header']);
        if ($kurang !== []) {
            return $this->gagal('Kolom wajib tidak ditemukan: '.implode(', ', $kurang).'. Gunakan templat.');
        }
        if ($tabel['rows'] === []) {
            return $this->gagal('Berkas tidak berisi baris soal.');
        }
        if (count($tabel['rows']) > self::MAKS_BARIS) {
            return $this->gagal('Maksimal '.self::MAKS_BARIS.' soal per berkas; pecah berkas menjadi beberapa bagian.');
        }
        if ((int) $exam->questions()->max('urutan') + count($tabel['rows']) > self::MAKS_URUTAN) {
            return $this->gagal('Jumlah soal ujian akan melebihi '.self::MAKS_URUTAN.'.');
        }

        $soal = [];
        $galat = [];
        foreach ($tabel['rows'] as ['line' => $line, 'data' => $data]) {
            [$hasil, $galatBaris] = $this->baris($data);
            if ($galatBaris !== []) {
                $galat[] = "Baris {$line}: ".implode('; ', $galatBaris).'.';
            } else {
                $soal[] = $hasil;
            }
        }

        return ['soal' => $galat === [] ? $soal : [], 'galat' => $galat];
    }

    /**
     * @param  array<string, string>  $data
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function baris(array $data): array
    {
        $ambil = fn (string $kolom) => $data[$kolom] ?? '';
        $tipe = QuestionType::tryFrom(strtolower($ambil('tipe')));
        if ($tipe === null) {
            return [[], ['tipe harus pg atau esai']];
        }

        $galat = [];
        $teks = $ambil('teks');
        if ($teks === '') {
            $galat[] = 'teks soal wajib diisi';
        } elseif (mb_strlen($teks) > 5000) {
            $galat[] = 'teks soal maksimal 5000 karakter';
        }

        $bobot = $tipe === QuestionType::Pg ? 1.0 : 10.0;
        if ($ambil('bobot') !== '') {
            $angka = str_replace(',', '.', $ambil('bobot'));
            if (is_numeric($angka) && (float) $angka >= 0.5 && (float) $angka <= 100) {
                $bobot = round((float) $angka, 2);
            } else {
                $galat[] = 'bobot harus angka 0,5–100';
            }
        }

        $soal = ['tipe' => $tipe, 'teks' => $teks, 'bobot' => $bobot, 'opsi' => [], 'kunci' => null, 'tetap' => [], 'kunci_esai' => null, 'keywords' => null];

        if ($tipe === QuestionType::Pg) {
            if ($ambil('kunci_esai') !== '' || $ambil('kata_kunci') !== '') {
                $galat[] = 'kolom kunci_esai/kata_kunci hanya untuk soal esai; periksa kolom tipe';
            }

            foreach (QuestionRequest::LABEL as $label) {
                $isi = $ambil('opsi_'.strtolower($label));
                if ($isi !== '') {
                    $soal['opsi'][$label] = $isi;
                    if (mb_strlen($isi) > 1000) {
                        $galat[] = 'opsi_'.strtolower($label).' maksimal 1000 karakter';
                    }
                }
            }
            $terisi = array_keys($soal['opsi']);
            $rentang = $terisi === [] ? 'A–E' : $terisi[0].'–'.end($terisi);

            if (! isset($soal['opsi']['A'], $soal['opsi']['B'])) {
                $galat[] = 'opsi_a dan opsi_b wajib diisi untuk soal pg';
            } elseif ($terisi !== array_slice(QuestionRequest::LABEL, 0, count($terisi))) {
                $galat[] = 'opsi harus diisi berurutan mulai dari A tanpa ada yang terlewat';
            }

            $kunci = strtoupper($ambil('kunci'));
            if ($kunci === '') {
                $galat[] = 'kunci wajib diisi untuk soal pg';
            } elseif (! in_array($kunci, $terisi, true)) {
                $galat[] = "kunci harus salah satu opsi yang terisi ({$rentang})";
            }
            $soal['kunci'] = $kunci;

            $tetap = array_values(array_unique(array_filter(
                array_map('trim', preg_split('/[,;\s]+/', strtoupper($ambil('opsi_tetap')))),
                fn ($l) => $l !== '',
            )));
            if (array_diff($tetap, $terisi) !== []) {
                $galat[] = "opsi_tetap harus label opsi yang terisi ({$rentang})";
            }
            $soal['tetap'] = $tetap;

            return [$soal, $galat];
        }

        $adaOpsi = array_filter(QuestionRequest::LABEL, fn ($l) => $ambil('opsi_'.strtolower($l)) !== '');
        if ($adaOpsi !== [] || $ambil('kunci') !== '' || $ambil('opsi_tetap') !== '') {
            $galat[] = 'kolom opsi/kunci/opsi_tetap hanya untuk soal pg; periksa kolom tipe';
        }

        $kunciEsai = $ambil('kunci_esai');
        if ($kunciEsai === '') {
            $galat[] = 'kunci_esai wajib diisi untuk soal esai';
        } elseif (mb_strlen($kunciEsai) > 5000) {
            $galat[] = 'kunci_esai maksimal 5000 karakter';
        }

        $kataKunci = QuestionRequest::pecahKataKunci($ambil('kata_kunci'));
        if (mb_strlen($ambil('kata_kunci')) > 2000) {
            $galat[] = 'kata_kunci maksimal 2000 karakter';
        } elseif (count($kataKunci) > QuestionRequest::MAKS_KATA_KUNCI) {
            $galat[] = 'kata_kunci maksimal '.QuestionRequest::MAKS_KATA_KUNCI.' butir';
        }

        $soal['kunci_esai'] = $kunciEsai;
        $soal['keywords'] = $kataKunci ?: null;

        return [$soal, $galat];
    }

    /**
     * Simpan semua soal dalam satu transaksi, berurutan setelah soal yang sudah ada.
     *
     * @param  list<array<string, mixed>>  $soal
     */
    public function simpan(Exam $exam, array $soal): int
    {
        return DB::transaction(function () use ($exam, $soal) {
            $urutan = (int) $exam->questions()->max('urutan');

            foreach ($soal as $s) {
                $question = $exam->questions()->create([
                    'tipe' => $s['tipe'],
                    'teks' => $s['teks'],
                    'bobot' => $s['bobot'],
                    'urutan' => ++$urutan,
                    'kunci_esai' => $s['kunci_esai'],
                    'keywords' => $s['keywords'],
                ]);

                foreach ($s['opsi'] as $label => $teks) {
                    $question->options()->create([
                        'label' => $label,
                        'teks' => $teks,
                        'is_correct' => $label === $s['kunci'],
                        'posisi_tetap' => in_array($label, $s['tetap'], true),
                    ]);
                }
            }

            return count($soal);
        });
    }

    /** @return array{soal: list<never>, galat: list<string>} */
    private function gagal(string $pesan): array
    {
        return ['soal' => [], 'galat' => [$pesan]];
    }
}
