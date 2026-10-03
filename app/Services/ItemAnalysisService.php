<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Support\Collection;

/**
 * Analisis butir soal (FR-09.4) dari attempt yang sudah final.
 *
 * - PG: indeks kesukaran p = benar / peserta yang mendapat soal (kosong = salah),
 *   sebaran pilihan per opsi, dan pengecoh (opsi salah) yang paling sering dipilih.
 * - Esai: rata-rata skor final / bobot dari jawaban yang sudah dikoreksi
 *   ("sementara" bila belum semua jawaban dikoreksi).
 * - Kategori: p >= 0,70 mudah; 0,30 <= p < 0,70 sedang; p < 0,30 sukar.
 */
class ItemAnalysisService
{
    /**
     * @return list<array{question: Question, peserta: int, benar: ?int, dikoreksi: ?int, sementara: bool, persen: ?float, kategori: ?string, sebaran: array<string, int>, kosong: int, pengecoh_terkuat: ?string}>
     */
    public function analisis(Exam $exam): array
    {
        $attempts = $exam->attempts()
            ->whereIn('status', [AttemptStatus::Selesai, AttemptStatus::Terkunci])
            ->get(['id', 'urutan_soal']);
        $jawaban = StudentAnswer::whereIn('attempt_id', $attempts->pluck('id'))
            ->get(['attempt_id', 'question_id', 'option_id', 'skor_final'])
            ->groupBy('question_id');

        $hasil = [];
        foreach ($exam->questions()->with('options')->get() as $question) {
            $penerima = $attempts->filter(fn (ExamAttempt $a) => in_array($question->id, $a->urutan_soal, true))->pluck('id')->all();
            $milikPenerima = ($jawaban[$question->id] ?? collect())->whereIn('attempt_id', $penerima);

            $hasil[] = $question->isPg()
                ? $this->pilihanGanda($question, count($penerima), $milikPenerima)
                : $this->esai($question, count($penerima), $milikPenerima);
        }

        return $hasil;
    }

    /** @param Collection<int, StudentAnswer> $jawaban */
    private function pilihanGanda(Question $question, int $peserta, Collection $jawaban): array
    {
        $labelPerOpsi = $question->options->pluck('label', 'id');
        $sebaran = $question->options->pluck('label')->mapWithKeys(fn (string $l) => [$l => 0])->all();
        foreach ($jawaban as $j) {
            if ($j->option_id !== null && isset($labelPerOpsi[$j->option_id])) {
                $sebaran[$labelPerOpsi[$j->option_id]]++;
            }
        }

        $kunci = $question->options->firstWhere('is_correct', true)?->label;
        $benar = $kunci ? $sebaran[$kunci] : 0;
        $pengecoh = collect($sebaran)->except($kunci)->filter()->sortDesc()->keys()->first();

        return $this->baris($question, $peserta, [
            'benar' => $benar,
            'dikoreksi' => null,
            'sementara' => false,
            'persen' => $peserta > 0 ? round($benar / $peserta * 100, 2) : null,
            'sebaran' => $sebaran,
            'kosong' => $peserta - array_sum($sebaran),
            'pengecoh_terkuat' => $pengecoh,
        ]);
    }

    /** @param Collection<int, StudentAnswer> $jawaban */
    private function esai(Question $question, int $peserta, Collection $jawaban): array
    {
        $dikoreksi = $jawaban->whereNotNull('skor_final');
        $persen = $dikoreksi->isNotEmpty() && $question->bobot > 0
            ? round($dikoreksi->sum('skor_final') / ($dikoreksi->count() * $question->bobot) * 100, 2)
            : null;

        return $this->baris($question, $peserta, [
            'benar' => null,
            'dikoreksi' => $dikoreksi->count(),
            'sementara' => $dikoreksi->count() < $peserta,
            'persen' => $persen,
            'sebaran' => [],
            'kosong' => 0,
            'pengecoh_terkuat' => null,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function baris(Question $question, int $peserta, array $data): array
    {
        $persen = $data['persen'];

        return ['question' => $question, 'peserta' => $peserta, ...$data, 'kategori' => match (true) {
            $persen === null => null,
            $persen >= 70 => 'mudah',
            $persen >= 30 => 'sedang',
            default => 'sukar',
        }];
    }
}
