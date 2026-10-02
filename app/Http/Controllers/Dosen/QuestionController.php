<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\QuestionRequest;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Bank soal per ujian (FR-02.2). Soal hanya dapat diubah selama ujian berstatus
 * draf, sehingga ujian yang sudah terbit tidak berubah diam-diam.
 */
class QuestionController extends Controller
{
    public function create(Request $request, Exam $exam): View|RedirectResponse
    {
        if ($tolak = $this->tolakBilaBukanDraf($exam)) {
            return $tolak;
        }

        $tipe = QuestionType::tryFrom((string) $request->query('tipe')) ?? QuestionType::Pg;

        return view('dosen.questions.create', [
            'exam' => $exam,
            'question' => new Question([
                'tipe' => $tipe,
                'bobot' => $tipe === QuestionType::Pg ? 1 : 10,
                'urutan' => (int) $exam->questions()->max('urutan') + 1,
            ]),
        ]);
    }

    public function store(QuestionRequest $request, Exam $exam): RedirectResponse
    {
        if ($tolak = $this->tolakBilaBukanDraf($exam)) {
            return $tolak;
        }

        DB::transaction(function () use ($request, $exam) {
            $question = $exam->questions()->create($request->dataSoal());
            $this->simpanOpsi($question, $request);
        });

        return redirect()->route('dosen.exams.show', $exam)->with('status', 'Soal ditambahkan.');
    }

    public function edit(Exam $exam, Question $question): View|RedirectResponse
    {
        if ($tolak = $this->tolakBilaBukanDraf($exam)) {
            return $tolak;
        }

        return view('dosen.questions.edit', ['exam' => $exam, 'question' => $question->load('options')]);
    }

    public function update(QuestionRequest $request, Exam $exam, Question $question): RedirectResponse
    {
        if ($tolak = $this->tolakBilaBukanDraf($exam)) {
            return $tolak;
        }

        DB::transaction(function () use ($request, $question) {
            $question->update($request->dataSoal());
            $this->simpanOpsi($question, $request);
        });

        return redirect()->route('dosen.exams.show', $exam)->with('status', 'Soal diperbarui.');
    }

    public function destroy(Exam $exam, Question $question): RedirectResponse
    {
        if ($tolak = $this->tolakBilaBukanDraf($exam)) {
            return $tolak;
        }

        $question->delete();

        return redirect()->route('dosen.exams.show', $exam)->with('status', 'Soal dihapus.');
    }

    /** Sinkronkan opsi berdasarkan label agar ID opsi yang tidak berubah tetap sama. */
    private function simpanOpsi(Question $question, QuestionRequest $request): void
    {
        if (! $question->isPg()) {
            $question->options()->delete();

            return;
        }

        $terisi = $request->labelTerisi();
        foreach ($terisi as $label) {
            $question->options()->updateOrCreate(['label' => $label], [
                'teks' => $request->input("opsi.{$label}.teks"),
                'is_correct' => $request->input('kunci') === $label,
                'posisi_tetap' => $request->boolean("opsi.{$label}.tetap"),
            ]);
        }
        $question->options()->whereNotIn('label', $terisi)->delete();
    }

    private function tolakBilaBukanDraf(Exam $exam): ?RedirectResponse
    {
        return $exam->isPublished() || $exam->sudahDikerjakan()
            ? redirect()->route('dosen.exams.show', $exam)->with('error', 'Soal hanya dapat diubah saat ujian berstatus draf. Tarik ujian ke draf terlebih dahulu.')
            : null;
    }
}
