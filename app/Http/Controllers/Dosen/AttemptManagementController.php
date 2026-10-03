<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\FinishReason;
use App\Exceptions\TindakanDitolak;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamLog;
use App\Services\AttemptService;
use App\Services\ViolationService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kelola attempt per mahasiswa (Task 4.8): maafkan/reset pelanggaran (FR-06.5),
 * tambah waktu dan buka ulang (FR-06.6). Semua tindakan wajib beralasan dan
 * tercatat di audit (siapa, kapan, alasan). Kepemilikan ujian lewat ExamPolicy
 * dan rute scoped (attempt milik ujian, log milik attempt).
 */
class AttemptManagementController extends Controller
{
    public function forgive(Request $request, Exam $exam, ExamAttempt $attempt, ExamLog $log, ViolationService $violations): RedirectResponse
    {
        $alasan = $this->alasan($request);

        return $this->jalankan($exam, $attempt, function () use ($request, $attempt, $log, $violations, $alasan) {
            $hasil = $violations->maafkan($log, $request->user(), $alasan);
            AuditLog::catat('pelanggaran_dimaafkan', $attempt, ['log_id' => $log->id, 'jenis' => $log->jenis->value, 'alasan' => $alasan]);

            return "Pelanggaran {$log->jenis->label()} pukul {$log->waktu->format('H:i:s')} dimaafkan. Pelanggaran terhitung kini {$hasil->jumlah_pelanggaran}/{$attempt->exam->batas_pelanggaran}.";
        });
    }

    public function reset(Request $request, Exam $exam, ExamAttempt $attempt, ViolationService $violations): RedirectResponse
    {
        $alasan = $this->alasan($request);

        return $this->jalankan($exam, $attempt, function () use ($request, $attempt, $violations, $alasan) {
            $jumlah = $violations->reset($attempt, $request->user(), $alasan);
            AuditLog::catat('pelanggaran_direset', $attempt, ['jumlah' => $jumlah, 'alasan' => $alasan]);

            return "{$jumlah} pelanggaran dimaafkan. Pelanggaran terhitung kini 0/{$attempt->exam->batas_pelanggaran}.";
        });
    }

    public function extend(Request $request, Exam $exam, ExamAttempt $attempt, AttemptService $attempts): RedirectResponse
    {
        [$menit, $alasan] = $this->menitDanAlasan($request);

        return $this->jalankan($exam, $attempt, function () use ($attempt, $attempts, $menit, $alasan) {
            $batas = $attempts->tambahWaktu($attempt, $menit);
            AuditLog::catat('waktu_ditambah', $attempt, ['menit' => $menit, 'alasan' => $alasan, 'batas_baru' => $batas->toIso8601String()]);

            return "Waktu {$attempt->user->nim_nidn} ditambah {$menit} menit; batas baru {$batas->format('H:i')} WIB.";
        });
    }

    public function reopen(Request $request, Exam $exam, ExamAttempt $attempt, AttemptService $attempts): RedirectResponse
    {
        [$menit, $alasan] = $this->menitDanAlasan($request);
        $sebelumnya = ['status_sebelumnya' => $attempt->status->value, 'alasan_selesai_sebelumnya' => $attempt->alasan_selesai?->value];

        return $this->jalankan($exam, $attempt, function () use ($attempt, $attempts, $menit, $alasan, $sebelumnya) {
            $batas = $attempts->bukaUlang($attempt, $menit);
            AuditLog::catat('attempt_dibuka_ulang', $attempt, [...$sebelumnya, 'menit' => $menit, 'alasan' => $alasan, 'batas_baru' => $batas->toIso8601String()]);

            return "Attempt {$attempt->user->nim_nidn} dibuka ulang sampai {$batas->format('H:i')} WIB. Mahasiswa dapat melanjutkan dari halaman ujiannya.";
        });
    }

    /**
     * FR-06.7: kunci/bekukan satu mahasiswa. Jawaban tersimpan dikirim dan dinilai
     * seperti biasa; layar ujian membeku pada permintaan berikutnya (≤ heartbeat).
     */
    public function lock(Request $request, Exam $exam, ExamAttempt $attempt, AttemptService $attempts): RedirectResponse
    {
        $alasan = $this->alasan($request);

        return $this->jalankan($exam, $attempt, function () use ($attempt, $attempts, $alasan) {
            if (! $attempts->selesaikan($attempt, FinishReason::DikunciDosen)) {
                throw new TindakanDitolak('Attempt ini sudah tidak berlangsung.');
            }
            AuditLog::catat('attempt_dikunci', $attempt, ['alasan' => $alasan]);

            return "Ujian {$attempt->user->nim_nidn} dikunci; jawaban tersimpan sudah dikirim. Layar mahasiswa membeku dalam ±".config('examguard.heartbeat_detik').' detik.';
        });
    }

    /** @param  Closure(): string  $tindakan */
    private function jalankan(Exam $exam, ExamAttempt $attempt, Closure $tindakan): RedirectResponse
    {
        $attempt->setRelation('exam', $exam);
        $tujuan = redirect()->route('dosen.reports.show', [$exam, $attempt]);

        try {
            return $tujuan->with('status', $tindakan());
        } catch (TindakanDitolak $e) {
            return $tujuan->with('error', $e->getMessage());
        }
    }

    private function alasan(Request $request): string
    {
        return $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:500']])['alasan'];
    }

    /** @return array{int, string} */
    private function menitDanAlasan(Request $request): array
    {
        $data = $request->validate([
            'menit' => ['required', 'integer', 'min:1', 'max:180'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return [(int) $data['menit'], $data['alasan']];
    }
}
