<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\AttemptService;
use App\Services\ItemAnalysisService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Analisis butir soal (FR-09.4); hanya dosen pemilik (rute can:kelola). */
class ItemAnalysisController extends Controller
{
    public function __invoke(Request $request, Exam $exam, ItemAnalysisService $analisis, AttemptService $attempts): View
    {
        // Attempt yang waktunya habis ditutup dulu agar ikut dihitung.
        $toleransi = (int) config('examguard.toleransi_simpan_detik');
        $exam->attempts()->where('status', AttemptStatus::Berlangsung)->get()
            ->each(fn ($attempt) => $attempts->finalisasiBilaKedaluwarsa($attempt->setRelation('exam', $exam), $toleransi));

        $butir = collect($analisis->analisis($exam))->map(fn (array $b, int $i) => [...$b, 'nomor' => $i + 1]);
        $sulitDulu = $request->query('urut') === 'sulit';
        if ($sulitDulu) {
            $butir = $butir->sortBy(fn (array $b) => $b['persen'] ?? INF)->values();
        }

        return view('dosen.reports.analysis', [
            'exam' => $exam,
            'butir' => $butir,
            'sulitDulu' => $sulitDulu,
            'ringkasan' => $butir->countBy(fn (array $b) => $b['kategori'] ?? 'belum'),
        ]);
    }
}
