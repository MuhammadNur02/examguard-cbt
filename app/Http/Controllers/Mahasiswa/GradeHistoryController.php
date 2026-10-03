<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Services\PublicationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Riwayat nilai (FR-07.3): nilai hanya ditampilkan setelah dipublikasikan.
 */
class GradeHistoryController extends Controller
{
    public function __invoke(Request $request, PublicationService $publikasi): View
    {
        // Publikasi terjadwal yang sudah jatuh tempo (FR-08.1), walau penjadwal belum berjalan.
        $publikasi->jalankanTerjadwal();

        $attempts = $request->user()->attempts()
            ->whereIn('status', [AttemptStatus::Selesai, AttemptStatus::Terkunci])
            ->with(['exam', 'result'])
            ->orderByDesc('selesai')
            ->get();

        return view('mahasiswa.grades', [
            'riwayat' => $attempts->map(fn ($attempt) => [
                'attempt' => $attempt,
                'nilai' => $attempt->result?->dipublikasikan_pada?->lessThanOrEqualTo(now())
                    ? $attempt->result->nilai_akhir
                    : null,
            ]),
        ]);
    }
}
