<?php

namespace App\Services;

use App\Enums\FinishReason;
use App\Enums\LogType;
use App\Exceptions\TindakanDitolak;
use App\Models\ExamAttempt;
use App\Models\ExamLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan pelanggaran sisi server (FR-04.1, FR-04.4–FR-04.6, PRD §9.1).
 *
 * - Kejadian pelanggaran dalam jarak debounce dari pelanggaran terhitung
 *   sebelumnya dianggap kejadian yang sama (visibilitychange + blur + keluar
 *   layar penuh dari satu aksi) dan tidak dihitung lagi.
 * - Penghitung = jumlah log terhitung yang tidak dimaafkan (K-7: satu penghitung
 *   untuk semua jenis).
 * - Pelanggaran ke-(N+1) memicu auto-submit dan status terkunci (K-1).
 */
class ViolationService
{
    public function __construct(private readonly AttemptService $attempts) {}

    /**
     * @param  array<string, mixed>  $detail
     * @return array{dihitung: bool, dikunci: bool}
     */
    public function catat(ExamAttempt $attempt, LogType $jenis, array $detail = []): array
    {
        if (! $attempt->isBerlangsung()) {
            return ['dihitung' => false, 'dikunci' => false];
        }

        $dihitung = DB::transaction(function () use ($attempt, $jenis, $detail) {
            // Serialkan pencatatan per attempt (row lock di MySQL/PostgreSQL).
            ExamAttempt::whereKey($attempt->id)->lockForUpdate()->first();

            $dihitung = $jenis->dihitung();
            if ($dihitung && $this->masihDalamDebounce($attempt)) {
                return false;
            }

            $attempt->logs()->create([
                'jenis' => $jenis,
                'waktu' => now(),
                'detail' => $detail ?: null,
                'dihitung' => $dihitung,
            ]);

            if ($dihitung) {
                $this->hitungUlang($attempt);
            }

            return $dihitung;
        });

        $attempt->refresh();
        $dikunci = false;
        if ($dihitung && $attempt->jumlah_pelanggaran > $attempt->exam->batas_pelanggaran) {
            $dikunci = $this->attempts->selesaikan($attempt, FinishReason::Pelanggaran);
        }

        return ['dihitung' => $dihitung, 'dikunci' => $dikunci];
    }

    /**
     * FR-06.5: maafkan satu pelanggaran terhitung (siapa, kapan, alasan) lalu
     * hitung ulang. Tidak membuka attempt yang sudah terkunci; itu tindakan terpisah.
     *
     * @throws TindakanDitolak
     */
    public function maafkan(ExamLog $log, User $dosen, string $alasan): ExamAttempt
    {
        return DB::transaction(function () use ($log, $dosen, $alasan) {
            $attempt = ExamAttempt::whereKey($log->attempt_id)->lockForUpdate()->firstOrFail();
            $log->refresh();

            if (! $log->dihitung) {
                throw new TindakanDitolak('Catatan ini adalah insiden yang tidak dihitung sebagai pelanggaran, jadi tidak perlu dimaafkan.');
            }
            if ($log->dimaafkan) {
                throw new TindakanDitolak('Pelanggaran ini sudah dimaafkan.');
            }

            $log->update(['dimaafkan' => true, 'dimaafkan_oleh' => $dosen->id, 'dimaafkan_pada' => now(), 'alasan' => $alasan]);
            $this->hitungUlang($attempt);

            return $attempt;
        });
    }

    /**
     * FR-06.5: maafkan semua pelanggaran terhitung pada attempt (reset ke 0).
     *
     * @throws TindakanDitolak
     */
    public function reset(ExamAttempt $attempt, User $dosen, string $alasan): int
    {
        return DB::transaction(function () use ($attempt, $dosen, $alasan) {
            ExamAttempt::whereKey($attempt->id)->lockForUpdate()->first();

            $jumlah = $attempt->logs()->where('dihitung', true)->where('dimaafkan', false)
                ->update(['dimaafkan' => true, 'dimaafkan_oleh' => $dosen->id, 'dimaafkan_pada' => now(), 'alasan' => $alasan]);
            if ($jumlah === 0) {
                throw new TindakanDitolak('Tidak ada pelanggaran terhitung yang perlu dimaafkan.');
            }
            $this->hitungUlang($attempt);

            return $jumlah;
        });
    }

    /** Sinkronkan penghitung di attempt dengan log yang terhitung dan tidak dimaafkan. */
    public function hitungUlang(ExamAttempt $attempt): int
    {
        $jumlah = $attempt->logs()->where('dihitung', true)->where('dimaafkan', false)->count();
        $attempt->forceFill(['jumlah_pelanggaran' => $jumlah])->save();

        return $jumlah;
    }

    private function masihDalamDebounce(ExamAttempt $attempt): bool
    {
        $terakhir = $attempt->logs()->where('dihitung', true)->max('waktu');
        if (! $terakhir) {
            return false;
        }

        $selisihMs = Carbon::parse($terakhir)->diffInMilliseconds(now(), true);

        return $selisihMs < config('examguard.debounce_pelanggaran_ms');
    }
}
