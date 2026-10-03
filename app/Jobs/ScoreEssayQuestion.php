<?php

namespace App\Jobs;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\SimilarityFlag;
use App\Models\StudentAnswer;
use App\Services\NlpClient;
use App\Services\ScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Skor rekomendasi untuk semua jawaban satu soal esai (Task 4.4).
 * skor_sistem = similarity × bobot; skor_final (keputusan dosen) tidak disentuh.
 */
class ScoreEssayQuestion implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $questionId) {}

    public function handle(NlpClient $nlp, ScoringService $scoring): void
    {
        $question = Question::find($this->questionId);
        if (! $question || $question->isPg() || blank($question->kunci_esai)) {
            return;
        }

        // Hanya attempt yang sudah final dan memuat soal ini.
        $attempts = ExamAttempt::where('exam_id', $question->exam_id)
            ->whereIn('status', [AttemptStatus::Selesai, AttemptStatus::Terkunci])
            ->get()
            ->filter(fn (ExamAttempt $attempt) => in_array($question->id, $attempt->urutan_soal, true));
        if ($attempts->isEmpty()) {
            return;
        }

        $answers = StudentAnswer::where('question_id', $question->id)
            ->whereIn('attempt_id', $attempts->pluck('id'))
            ->get()
            ->keyBy('attempt_id');
        $berisi = $answers->filter(fn (StudentAnswer $a) => filled($a->teks_jawaban));

        // Seluruh jawaban berisi dikirim sekaligus: IDF dari kunci + semua jawaban (K-2).
        $hasil = $berisi->isEmpty() ? [] : $nlp->skor(
            $question->kunci_esai,
            $berisi->map(fn (StudentAnswer $a) => ['id' => $a->id, 'teks' => $a->teks_jawaban])->values()->all(),
            $question->keywords ?? [],
        );

        DB::transaction(function () use ($attempts, $answers, $hasil, $question, $scoring) {
            foreach ($attempts as $attempt) {
                $answer = $answers->get($attempt->id);

                if ($answer && filled($answer->teks_jawaban)) {
                    $baris = $hasil[$answer->id] ?? null;
                    if ($baris !== null) {
                        $answer->update([
                            'similarity' => round($baris['similarity'], 4),
                            'kata_kunci_cocok' => $baris['kata_kunci_terpenuhi'],
                            'skor_sistem' => round($baris['similarity'] * $question->bobot, 2),
                        ]);
                    }
                } elseif (! $answer || $answer->skor_sistem === null) {
                    // Esai tidak dijawab: tidak ada yang perlu dinilai, skor 0.
                    $attempt->answers()->updateOrCreate(['question_id' => $question->id], [
                        'similarity' => 0, 'skor_sistem' => 0, 'skor_final' => 0,
                    ]);
                }

                $scoring->perbaruiHasil($attempt);
            }
        });

        $this->perbaruiTandaKemiripan($nlp, $question, $berisi);
    }

    /**
     * FR-05.5: tandai pasangan jawaban antarmahasiswa yang mirip. Tanda lama
     * soal ini diganti seluruhnya agar selalu sesuai jawaban terakhir.
     *
     * @param  Collection<int, StudentAnswer>  $berisi
     */
    private function perbaruiTandaKemiripan(NlpClient $nlp, Question $question, Collection $berisi): void
    {
        $pasangan = $berisi->count() < 2 ? [] : $nlp->kemiripan(
            $berisi->map(fn (StudentAnswer $a) => ['id' => $a->id, 'teks' => $a->teks_jawaban])->values()->all(),
            (float) config('examguard.ambang_kemiripan_esai'),
            (int) config('examguard.min_token_kemiripan'),
        );
        $attemptPerJawaban = $berisi->mapWithKeys(fn (StudentAnswer $a) => [$a->id => $a->attempt_id]);

        DB::transaction(function () use ($question, $pasangan, $attemptPerJawaban) {
            SimilarityFlag::where('question_id', $question->id)->delete();

            foreach ($pasangan as ['a' => $a, 'b' => $b, 'skor' => $skor]) {
                $x = $attemptPerJawaban->get($a);
                $y = $attemptPerJawaban->get($b);
                if ($x === null || $y === null || $x === $y) {
                    continue;
                }
                SimilarityFlag::firstOrCreate(
                    ['question_id' => $question->id, 'attempt_a' => min($x, $y), 'attempt_b' => max($x, $y)],
                    ['skor' => round($skor, 4)],
                );
            }
        });
    }
}
