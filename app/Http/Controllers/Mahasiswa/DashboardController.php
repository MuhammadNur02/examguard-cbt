<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\AttemptService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AttemptService $attempts): View
    {
        $user = $request->user()->load('kelas');
        $milikSaya = $user->attempts()->get()->keyBy('exam_id');
        $toleransi = (int) config('examguard.toleransi_simpan_detik');

        // Hanya ujian terbit untuk kelas mahasiswa ini atau tanpa kelas (FR-02.3, FR-02.6).
        $daftar = Exam::terlihatUntuk($user)
            ->orderByDesc('mulai')
            ->get()
            ->map(function (Exam $exam) use ($milikSaya, $attempts, $toleransi) {
                $attempt = $milikSaya->get($exam->id)?->setRelation('exam', $exam);
                if ($attempt) {
                    $attempts->finalisasiBilaKedaluwarsa($attempt, $toleransi);
                }

                return ['exam' => $exam, 'keadaan' => $attempts->keadaan($exam, $attempt)];
            });

        return view('mahasiswa.dashboard', ['user' => $user, 'daftar' => $daftar]);
    }
}
