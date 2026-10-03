<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\AttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman ujian mahasiswa: halaman masuk (info + persetujuan integritas) dan
 * layar pengerjaan. Data soal diambil layar ujian lewat AttemptController.
 */
class ExamController extends Controller
{
    public function __construct(private readonly AttemptService $attempts) {}

    public function show(Request $request, Exam $exam): View
    {
        abort_unless($exam->terlihatOleh($request->user()), 404);

        $attempt = $this->attempts->attemptMilik($exam, $request->user());
        if ($attempt) {
            $this->attempts->finalisasiBilaKedaluwarsa($attempt, (int) config('examguard.toleransi_simpan_detik'));
        }

        return view('mahasiswa.exams.show', [
            'exam' => $exam,
            'attempt' => $attempt,
            'keadaan' => $this->attempts->keadaan($exam, $attempt),
            'pesanSelesai' => $attempt && ! $attempt->isBerlangsung() ? $this->attempts->pesanSelesai($attempt) : null,
        ]);
    }

    public function work(Request $request, Exam $exam): View|RedirectResponse
    {
        abort_unless($exam->terlihatOleh($request->user()), 404);

        $attempt = $this->attempts->attemptMilik($exam, $request->user());
        if (! $attempt) {
            return redirect()->route('mahasiswa.exams.show', $exam);
        }

        $this->attempts->finalisasiBilaKedaluwarsa($attempt, (int) config('examguard.toleransi_simpan_detik'));
        if (! $attempt->isBerlangsung()) {
            return redirect()->route('mahasiswa.exams.show', $exam)->with('status', $this->attempts->pesanSelesai($attempt));
        }

        return view('mahasiswa.exams.work', [
            'exam' => $exam,
            'konfigurasi' => [
                'url' => [
                    'soal' => route('mahasiswa.attempts.questions', $exam),
                    'jawaban' => route('mahasiswa.attempts.answers', $exam),
                    'heartbeat' => route('mahasiswa.attempts.heartbeat', $exam),
                    'pelanggaran' => route('mahasiswa.attempts.violation', $exam),
                    'kirim' => route('mahasiswa.attempts.submit', $exam),
                    'selesai' => route('mahasiswa.exams.show', $exam),
                ],
                'heartbeatDetik' => (int) config('examguard.heartbeat_detik'),
                'autosaveDetik' => (int) config('examguard.autosave_detik'),
                'debounceMs' => (int) config('examguard.debounce_pelanggaran_ms'),
                'kunciPenyimpanan' => 'examguard-antrean-'.$attempt->id,
            ],
        ]);
    }
}
