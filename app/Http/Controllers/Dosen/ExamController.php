<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\ExamRequest;
use App\Models\AuditLog;
use App\Models\Exam;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Kelola ujian (FR-02.1, FR-02.3). Otorisasi kepemilikan lewat ExamPolicy@kelola
 * pada rute. Ujian yang sudah dikerjakan mahasiswa tidak dapat diubah/dihapus.
 */
class ExamController extends Controller
{
    public function create(): View
    {
        return view('dosen.exams.create', [
            'exam' => new Exam(['durasi_menit' => 90, 'batas_pelanggaran' => 3, 'acak_soal' => true, 'acak_opsi' => true]),
        ]);
    }

    public function store(ExamRequest $request): RedirectResponse
    {
        $exam = $request->user()->exams()->create([...$request->dataUjian(), 'status' => ExamStatus::Draft]);

        return redirect()->route('dosen.exams.show', $exam)
            ->with('status', 'Ujian dibuat sebagai draf. Tambahkan soal, lalu terbitkan.');
    }

    public function show(Exam $exam): View
    {
        $exam->load('questions.options')->loadCount('attempts');

        return view('dosen.exams.show', [
            'exam' => $exam,
            'masalah' => $exam->isPublished() ? [] : $exam->masalahPublikasi(),
        ]);
    }

    public function edit(Exam $exam): View|RedirectResponse
    {
        return $this->tolakBilaSudahDikerjakan($exam) ?? view('dosen.exams.edit', ['exam' => $exam]);
    }

    public function update(ExamRequest $request, Exam $exam): RedirectResponse
    {
        if ($tolak = $this->tolakBilaSudahDikerjakan($exam)) {
            return $tolak;
        }

        $exam->update($request->dataUjian());

        return redirect()->route('dosen.exams.show', $exam)->with('status', 'Ujian diperbarui.');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        if ($tolak = $this->tolakBilaSudahDikerjakan($exam)) {
            return $tolak;
        }

        $exam->delete();

        return redirect()->route('dosen.dashboard')->with('status', "Ujian \"{$exam->judul}\" dihapus.");
    }

    public function publish(Exam $exam): RedirectResponse
    {
        $masalah = $exam->masalahPublikasi();
        if ($masalah !== []) {
            return back()->with('error', 'Ujian belum dapat diterbitkan. '.implode(' ', $masalah));
        }

        $exam->update(['status' => ExamStatus::Published]);
        AuditLog::catat('ujian_diterbitkan', $exam);

        return back()->with('status', 'Ujian diterbitkan dan kini terlihat oleh mahasiswa.');
    }

    public function unpublish(Exam $exam): RedirectResponse
    {
        if ($exam->sudahDikerjakan()) {
            return back()->with('error', 'Ujian yang sudah dikerjakan mahasiswa tidak dapat ditarik kembali.');
        }

        $exam->update(['status' => ExamStatus::Draft]);
        AuditLog::catat('ujian_ditarik', $exam);

        return back()->with('status', 'Ujian dikembalikan ke draf dan tidak terlihat oleh mahasiswa.');
    }

    private function tolakBilaSudahDikerjakan(Exam $exam): ?RedirectResponse
    {
        return $exam->sudahDikerjakan()
            ? redirect()->route('dosen.exams.show', $exam)->with('error', 'Ujian sudah dikerjakan mahasiswa sehingga tidak dapat diubah atau dihapus.')
            : null;
    }
}
