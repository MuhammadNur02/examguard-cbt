<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ExamResult;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * Penilaian di backend (FR-05.1) dan rekap hasil per attempt.
 *
 * - PG: skor = bobot bila opsi benar, 0 bila salah; langsung final.
 * - Esai kosong: skor 0 otomatis (tidak ada yang perlu dinilai dosen).
 * - Esai terisi: skor rekomendasi dari layanan NLP, skor final dari dosen.
 * - Nilai akhir = (skor PG + skor esai final) / skor maksimal × 100,
 *   terisi setelah semua esai pada attempt final (DECISIONS D-17).
 */
class ScoringService
{
    /** Nilai otomatis saat attempt difinalisasi. Aman dipanggil berulang. */
    public function nilaiOtomatis(ExamAttempt $attempt): ExamResult
    {
        return DB::transaction(function () use ($attempt) {
            $questions = Question::with('options')->whereIn('id', $attempt->urutan_soal)->get();
            $answers = $attempt->answers()->get()->keyBy('question_id');

            foreach ($questions as $question) {
                $answer = $answers->get($question->id);

                if ($question->isPg()) {
                    if (! $answer) {
                        continue;
                    }
                    $benar = $answer->option_id !== null
                        && (bool) $question->options->firstWhere('id', $answer->option_id)?->is_correct;
                    $skor = $benar ? $question->bobot : 0.0;
                    $answer->update(['skor_sistem' => $skor, 'skor_final' => $skor]);
                } elseif (blank($answer?->teks_jawaban)) {
                    $attempt->answers()->updateOrCreate(['question_id' => $question->id], [
                        'similarity' => 0,
                        'skor_sistem' => 0,
                        'skor_final' => 0,
                    ]);
                }
            }

            return $this->perbaruiHasil($attempt);
        });
    }

    /** Hitung ulang exam_results dari skor per jawaban. */
    public function perbaruiHasil(ExamAttempt $attempt): ExamResult
    {
        $questions = Question::whereIn('id', $attempt->urutan_soal)->get(['id', 'tipe', 'bobot']);
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $skorPg = 0.0;
        $skorEsaiSistem = 0.0;
        $skorEsaiFinal = 0.0;
        $adaEsai = false;
        $esaiLengkapSistem = true;
        $esaiFinal = true;

        foreach ($questions as $question) {
            $answer = $answers->get($question->id);
            if ($question->isPg()) {
                $skorPg += $answer?->skor_final ?? 0.0;

                continue;
            }

            $adaEsai = true;
            if ($answer?->skor_sistem === null) {
                $esaiLengkapSistem = false;
            } else {
                $skorEsaiSistem += $answer->skor_sistem;
            }
            if ($answer?->skor_final === null) {
                $esaiFinal = false;
            } else {
                $skorEsaiFinal += $answer->skor_final;
            }
        }

        $skorMaksimal = (float) $questions->sum('bobot');
        $siapDinilai = ! $adaEsai || $esaiFinal;

        return $attempt->result()->updateOrCreate([], [
            'skor_pg' => round($skorPg, 2),
            'skor_esai_sistem' => $adaEsai && $esaiLengkapSistem ? round($skorEsaiSistem, 2) : null,
            'skor_esai_final' => $adaEsai && $esaiFinal ? round($skorEsaiFinal, 2) : null,
            'skor_maksimal' => round($skorMaksimal, 2),
            'nilai_akhir' => $siapDinilai && $skorMaksimal > 0
                ? round(($skorPg + ($adaEsai ? $skorEsaiFinal : 0)) / $skorMaksimal * 100, 2)
                : null,
        ]);
    }
}
