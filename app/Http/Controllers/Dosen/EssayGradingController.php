<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Exceptions\NlpTidakTersedia;
use App\Http\Controllers\Controller;
use App\Jobs\ScoreEssayQuestion;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Services\AttemptService;
use Illuminate\Http\RedirectResponse;

/**
 * Penilaian esai oleh dosen: memicu skor rekomendasi NLP (Task 4.4) dan
 * koreksi berdampingan (Task 4.6).
 */
class EssayGradingController extends Controller
{
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
}
