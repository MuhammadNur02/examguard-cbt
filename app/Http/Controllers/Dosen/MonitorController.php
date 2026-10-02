<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamLog;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Live Monitor (FR-06.1, FR-06.2, K-4): status peserta dan pelanggaran baru,
 * diambil halaman dosen lewat polling.
 */
class MonitorController extends Controller
{
    public function show(Exam $exam): View
    {
        return view('dosen.exams.monitor', [
            'exam' => $exam,
            'konfigurasi' => [
                'url' => route('dosen.monitor.data', $exam),
                'pollDetik' => min(10, max(2, (int) config('examguard.monitor_poll_detik'))),
            ],
        ]);
    }

    public function data(Request $request, Exam $exam, AttemptService $attempts): JsonResponse
    {
        $toleransi = (int) config('examguard.toleransi_simpan_detik');
        $offlineSetelah = (int) config('examguard.offline_setelah_detik');

        // Tutup dulu attempt yang waktunya habis, baru ambil data beserta hitungannya.
        $exam->attempts()->where('status', AttemptStatus::Berlangsung)->get()
            ->each(fn (ExamAttempt $attempt) => $attempts->finalisasiBilaKedaluwarsa($attempt->setRelation('exam', $exam), $toleransi));

        $daftar = $exam->attempts()
            ->with('user:id,nim_nidn,nama')
            ->withCount(['answers as terjawab_count' => fn ($q) => $q->where(fn ($w) => $w
                ->whereNotNull('option_id')
                ->orWhere(fn ($e) => $e->whereNotNull('teks_jawaban')->where('teks_jawaban', '!=', '')))])
            ->get()
            ->each(fn (ExamAttempt $attempt) => $attempt->setRelation('exam', $exam));

        $peserta = $daftar->map(function (ExamAttempt $attempt) use ($exam, $offlineSetelah) {
            $status = match (true) {
                $attempt->status === AttemptStatus::Terkunci => 'terkunci',
                $attempt->status === AttemptStatus::Selesai => 'selesai',
                $attempt->terakhir_aktif?->greaterThanOrEqualTo(now()->subSeconds($offlineSetelah)) => 'aktif',
                default => 'offline',
            };

            return [
                'id' => $attempt->id,
                'nim' => $attempt->user->nim_nidn,
                'nama' => $attempt->user->nama,
                'status' => $status,
                'pelanggaran' => $attempt->jumlah_pelanggaran,
                'batas' => $exam->batas_pelanggaran,
                'terjawab' => $attempt->terjawab_count,
                'jumlah_soal' => count($attempt->urutan_soal),
                'terakhir_aktif' => $attempt->terakhir_aktif?->format('H:i:s'),
                'sisa_detik' => $attempt->isBerlangsung() ? $attempt->sisaDetik() : 0,
                'selesai' => $attempt->selesai?->format('H:i'),
            ];
        })->sortBy([['status', 'asc'], ['nim', 'asc']])->values();

        return response()->json([
            'waktu_server' => now()->format('H:i:s'),
            'ringkasan' => [
                'aktif' => $peserta->where('status', 'aktif')->count(),
                'offline' => $peserta->where('status', 'offline')->count(),
                'selesai' => $peserta->where('status', 'selesai')->count(),
                'terkunci' => $peserta->where('status', 'terkunci')->count(),
            ],
            'peserta' => $peserta,
            'pelanggaran_baru' => $this->pelanggaranBaru($exam, (int) $request->query('sejak', 0)),
        ]);
    }

    /** Log pelanggaran setelah id tertentu; panggilan pertama memuat 20 terakhir. */
    private function pelanggaranBaru(Exam $exam, int $sejak): array
    {
        return ExamLog::whereHas('attempt', fn ($q) => $q->where('exam_id', $exam->id))
            ->where('id', '>', $sejak)
            ->with('attempt.user:id,nim_nidn,nama')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ExamLog $log) => [
                'id' => $log->id,
                'nim' => $log->attempt->user->nim_nidn,
                'nama' => $log->attempt->user->nama,
                'jenis' => $log->jenis->value,
                'jenis_label' => $log->jenis->label(),
                'dihitung' => $log->dihitung,
                'waktu' => $log->waktu->format('H:i:s'),
            ])
            ->all();
    }
}
