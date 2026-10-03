<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Exceptions\NlpTidakTersedia;
use App\Http\Controllers\Controller;
use App\Jobs\ScoreEssayQuestion;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Question;
use App\Models\SimilarityFlag;
use App\Models\StudentAnswer;
use App\Services\AttemptService;
use App\Services\ScoringService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Penilaian esai oleh dosen: memicu skor rekomendasi NLP (Task 4.4) dan
 * koreksi berdampingan dengan konfirmasi/ubah skor (Task 4.6, FR-06.3).
 * Yang tersimpan sebagai nilai akhir selalu keputusan dosen (PRD §9.3 langkah 5).
 */
class EssayGradingController extends Controller
{
    public function index(Exam $exam): View
    {
        $soal = $exam->questions()->where('tipe', QuestionType::Esai)->get()->map(function (Question $question) {
            $jawaban = $this->jawabanFinal($question);

            return [
                'question' => $question,
                'total' => $jawaban->count(),
                'direkomendasikan' => $jawaban->whereNotNull('skor_sistem')->count(),
                'dikonfirmasi' => $jawaban->whereNotNull('skor_final')->count(),
                'mirip' => SimilarityFlag::where('question_id', $question->id)->count(),
            ];
        });

        return view('dosen.grading.index', ['exam' => $exam, 'soal' => $soal]);
    }

    public function show(Request $request, Exam $exam, Question $question): View
    {
        abort_if($question->isPg(), 404);

        $jawaban = $this->jawabanFinal($question);
        $dipilih = $request->filled('jawaban')
            ? $jawaban->firstWhere('id', (int) $request->query('jawaban'))
            : ($jawaban->first(fn (StudentAnswer $a) => $a->skor_final === null) ?? $jawaban->first());

        abort_if($request->filled('jawaban') && ! $dipilih, 404);

        $adaKataKunci = filled($question->keywords);
        $opsiAmbang = collect(config('examguard.ambang_terima_massal'))->mapWithKeys(fn (float $ambang) => [(string) $ambang => [
            'dengan_kata_kunci' => $this->calonTerimaMassal($jawaban, $question, $ambang, $adaKataKunci)->count(),
            'tanpa_syarat' => $this->calonTerimaMassal($jawaban, $question, $ambang, false)->count(),
        ]]);

        return view('dosen.grading.show', [
            'exam' => $exam,
            'question' => $question,
            'jawaban' => $jawaban,
            'dipilih' => $dipilih,
            'posisi' => $dipilih ? $jawaban->search(fn ($a) => $a->id === $dipilih->id) : null,
            'opsiAmbang' => $opsiAmbang,
            'ambangBawaan' => (float) config('examguard.ambang_terima_massal_bawaan'),
            'kemiripan' => $this->pasanganMirip($question, $jawaban),
        ]);
    }

    /**
     * Pasangan jawaban mirip antarmahasiswa (FR-05.5) beserta jawabannya.
     *
     * @param  Collection<int, StudentAnswer>  $jawaban
     * @return Collection<int, array{skor: float, a: StudentAnswer, b: StudentAnswer}>
     */
    private function pasanganMirip(Question $question, Collection $jawaban): Collection
    {
        $perAttempt = $jawaban->keyBy('attempt_id');

        return SimilarityFlag::where('question_id', $question->id)->orderByDesc('skor')->get()
            ->map(fn (SimilarityFlag $f) => ['skor' => $f->skor, 'a' => $perAttempt->get($f->attempt_a), 'b' => $perAttempt->get($f->attempt_b)])
            ->filter(fn (array $p) => $p['a'] && $p['b'])
            ->values();
    }

    /**
     * Koreksi cepat (FR-06.4): terima rekomendasi sekaligus untuk jawaban yang
     * belum dikonfirmasi dengan similarity ≥ ambang (opsional: semua kata kunci
     * terpenuhi). Keputusan dosen yang sudah ada tidak pernah ditimpa.
     */
    public function terimaMassal(Request $request, Exam $exam, Question $question, ScoringService $scoring): RedirectResponse
    {
        abort_if($question->isPg(), 404);
        $data = $request->validate([
            'ambang' => ['required', 'numeric', 'min:0.5', 'max:1'],
            'wajib_kata_kunci' => ['sometimes', 'boolean'],
        ], [], ['ambang' => 'ambang similarity']);

        $ambang = round((float) $data['ambang'], 2);
        $wajibKataKunci = $request->boolean('wajib_kata_kunci') && filled($question->keywords);
        $jawaban = $this->jawabanFinal($question);
        $calon = $this->calonTerimaMassal($jawaban, $question, $ambang, $wajibKataKunci);
        $syarat = 'similarity ≥ '.number_format($ambang, 2, ',', '.').($wajibKataKunci ? ', semua kata kunci terpenuhi' : '');

        if ($calon->isEmpty()) {
            return back()->with('error', "Tidak ada jawaban belum dikonfirmasi yang memenuhi syarat ({$syarat}).");
        }

        DB::transaction(function () use ($calon, $request, $scoring) {
            foreach ($calon as $answer) {
                $answer->update(['skor_final' => $answer->skor_sistem, 'dinilai_oleh' => $request->user()->id, 'dinilai_pada' => now()]);
                $scoring->perbaruiHasil($answer->attempt);
            }
        });

        AuditLog::catat('skor_esai_diterima_massal', $question, [
            'ambang' => $ambang,
            'wajib_kata_kunci' => $wajibKataKunci,
            'jumlah' => $calon->count(),
            'skor' => $calon->mapWithKeys(fn (StudentAnswer $a) => [$a->id => $a->skor_final])->all(),
        ]);

        $sisa = $jawaban->filter(fn (StudentAnswer $a) => $a->skor_final === null)->count();

        return redirect()->route('dosen.grading.show', [$exam, $question])
            ->with('status', "{$calon->count()} rekomendasi diterima ({$syarat}). {$sisa} jawaban lain belum dikonfirmasi.");
    }

    /** @param  Collection<int, StudentAnswer>  $jawaban  @return Collection<int, StudentAnswer> */
    private function calonTerimaMassal(Collection $jawaban, Question $question, float $ambang, bool $wajibKataKunci): Collection
    {
        $kataKunci = $question->keywords ?? [];

        return $jawaban->filter(fn (StudentAnswer $a) => $a->skor_final === null
            && $a->skor_sistem !== null
            && $a->similarity !== null
            && round($a->similarity, 4) >= round($ambang, 4)
            && (! $wajibKataKunci || array_diff($kataKunci, $a->kata_kunci_cocok ?? []) === []))->values();
    }

    public function update(Request $request, Exam $exam, Question $question, StudentAnswer $answer, ScoringService $scoring): RedirectResponse
    {
        abort_if($question->isPg(), 404);
        $jawaban = $this->jawabanFinal($question);
        abort_unless($jawaban->contains('id', $answer->id), 404);

        $data = $request->validate([
            'aksi' => ['required', 'in:setujui,simpan'],
            'skor' => ['required_if:aksi,simpan', 'nullable', 'numeric', 'min:0', 'max:'.$question->bobot],
        ], [], ['skor' => 'skor']);

        if ($data['aksi'] === 'setujui' && $answer->skor_sistem === null) {
            return back()->with('error', 'Skor rekomendasi belum tersedia. Hitung skor rekomendasi terlebih dahulu atau isi skor secara manual.');
        }

        $skor = $data['aksi'] === 'setujui' ? $answer->skor_sistem : round((float) $data['skor'], 2);
        $skorLama = $answer->skor_final;
        $answer->update(['skor_final' => $skor, 'dinilai_oleh' => $request->user()->id, 'dinilai_pada' => now()]);
        $hasil = $scoring->perbaruiHasil($answer->attempt);

        if ($skorLama !== $skor) {
            // Jejak perubahan nilai (siapa, kapan, dari-ke), termasuk setelah publikasi.
            AuditLog::catat('skor_esai_diubah', $answer, [
                'dari' => $skorLama,
                'ke' => $skor,
                'rekomendasi' => $answer->skor_sistem,
                'sudah_dipublikasikan' => $hasil->dipublikasikan_pada !== null,
            ]);
        }

        // Perbandingan ketat: skor final 0 (esai kosong) bukan "belum dikonfirmasi".
        $berikutnya = $jawaban->skip($jawaban->search(fn ($a) => $a->id === $answer->id) + 1)
            ->first(fn (StudentAnswer $a) => $a->skor_final === null);

        return redirect()
            ->route('dosen.grading.show', [$exam, $question, 'jawaban' => $berikutnya?->id ?? $answer->id])
            ->with('status', 'Skor '.$answer->attempt->user->nim_nidn.' disimpan: '.Format::angka($skor).'.');
    }

    /** Hitung skor rekomendasi semua soal esai lewat antrean. */
    public function hitung(Exam $exam, AttemptService $attempts): RedirectResponse
    {
        $toleransi = (int) config('examguard.toleransi_simpan_detik');
        $exam->attempts()->where('status', AttemptStatus::Berlangsung)->get()
            ->each(fn ($attempt) => $attempts->finalisasiBilaKedaluwarsa($attempt->setRelation('exam', $exam), $toleransi));

        if ($exam->attempts()->where('status', AttemptStatus::Berlangsung)->exists()) {
            return back()->with('error', 'Masih ada peserta yang mengerjakan. Skor rekomendasi dihitung setelah semua peserta selesai, karena IDF memakai seluruh jawaban (K-2).');
        }

        $soalEsai = $exam->questions()->where('tipe', QuestionType::Esai)->pluck('id');
        if ($soalEsai->isEmpty()) {
            return back()->with('error', 'Ujian ini tidak memiliki soal esai.');
        }

        try {
            $soalEsai->each(fn (int $id) => ScoreEssayQuestion::dispatch($id));
        } catch (NlpTidakTersedia $e) {
            // Hanya terjadi bila antrean berjalan sinkron.
            return back()->with('error', 'Layanan NLP tidak dapat dihubungi. Pastikan layanan berjalan lalu coba lagi.');
        }

        AuditLog::catat('skor_esai_dihitung', $exam, ['jumlah_soal' => $soalEsai->count()]);

        return back()->with('status', 'Penghitungan skor rekomendasi dimasukkan ke antrean. Pastikan queue worker dan layanan NLP berjalan; muat ulang halaman untuk melihat hasil.');
    }

    /** Jawaban soal ini dari attempt yang sudah final, urut NIM. @return Collection<int, StudentAnswer> */
    private function jawabanFinal(Question $question): Collection
    {
        return StudentAnswer::where('question_id', $question->id)
            ->whereHas('attempt', fn ($q) => $q->whereIn('status', [AttemptStatus::Selesai, AttemptStatus::Terkunci]))
            ->with('attempt.user:id,nim_nidn,nama')
            ->get()
            ->sortBy(fn (StudentAnswer $a) => $a->attempt->user->nim_nidn)
            ->values();
    }
}
