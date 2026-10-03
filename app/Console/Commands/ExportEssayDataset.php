<?php

namespace App\Console\Commands;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\StudentAnswer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Ekspor dataset esai untuk uji akurasi (Task 5.3, PRD §13.2).
 *
 * Kolom skor_dosen dikosongkan agar dosen menilai tanpa melihat rekomendasi
 * sistem (menghindari bias jangkar). Identitas mahasiswa tidak diekspor.
 */
#[Signature('ujian:ekspor-esai
    {exam : ID ujian}
    {--keluar= : Lokasi berkas CSV (bawaan storage/app/private/evaluasi/esai-ujian-{id}.csv)}
    {--sertakan-skor-final : Isi skor_dosen dari skor final di sistem (berisiko bias bila dosen melihat rekomendasi)}')]
#[Description('Ekspor jawaban esai (anonim) ke CSV untuk evaluasi MAE dan Pearson.')]
class ExportEssayDataset extends Command
{
    public const KOLOM = ['soal_id', 'bobot', 'kunci', 'jawaban_id', 'kode_mahasiswa', 'jawaban', 'skor_dosen'];

    public function handle(): int
    {
        $exam = Exam::find($this->argument('exam'));
        if (! $exam) {
            $this->error('Ujian tidak ditemukan.');

            return self::FAILURE;
        }

        $path = $this->option('keluar') ?: storage_path("app/private/evaluasi/esai-ujian-{$exam->id}.csv");
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $kode = [];
        $jumlah = 0;
        $berkas = fopen($path, 'w');
        fwrite($berkas, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        fputcsv($berkas, self::KOLOM, ',', '"', '');

        foreach ($exam->questions()->where('tipe', QuestionType::Esai)->get() as $question) {
            $jawaban = StudentAnswer::where('question_id', $question->id)
                ->whereNotNull('teks_jawaban')
                ->where('teks_jawaban', '!=', '')
                ->whereHas('attempt', fn ($q) => $q->whereIn('status', [AttemptStatus::Selesai, AttemptStatus::Terkunci]))
                ->orderBy('id')
                ->get();

            foreach ($jawaban as $answer) {
                $kode[$answer->attempt_id] ??= sprintf('M%03d', count($kode) + 1);
                fputcsv($berkas, [
                    $question->id,
                    $question->bobot,
                    $this->teks($question->kunci_esai),
                    $answer->id,
                    $kode[$answer->attempt_id],
                    $this->teks($answer->teks_jawaban),
                    $this->option('sertakan-skor-final') ? $answer->skor_final : '',
                ], ',', '"', '');
                $jumlah++;
            }
        }

        fclose($berkas);
        $this->info("{$jumlah} jawaban esai diekspor ke {$path}");
        if ($this->option('sertakan-skor-final')) {
            $this->warn('skor_dosen diisi dari skor final sistem. Bila dosen melihat rekomendasi saat menilai, korelasi bisa terlalu tinggi (bias jangkar).');
        }

        return self::SUCCESS;
    }

    /** Awalan ' mencegah Excel menjalankan teks berawalan = + - @ sebagai formula. */
    private function teks(?string $teks): string
    {
        $teks = (string) $teks;

        return preg_match('/^[=+\-@\t\r]/', $teks) ? "'".$teks : $teks;
    }
}
