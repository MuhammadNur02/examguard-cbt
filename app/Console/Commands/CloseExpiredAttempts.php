<?php

namespace App\Console\Commands;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Services\AttemptService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Kirim otomatis attempt yang melewati batas waktu walau peramban mahasiswa
 * sudah tertutup. Dijadwalkan tiap menit (routes/console.php).
 */
#[Signature('ujian:tutup-kedaluwarsa')]
#[Description('Tutup attempt yang melewati batas waktu sebagai "waktu habis".')]
class CloseExpiredAttempts extends Command
{
    public function handle(AttemptService $attempts): int
    {
        $ditutup = 0;
        $toleransi = (int) config('examguard.toleransi_simpan_detik');

        ExamAttempt::with('exam')
            ->where('status', AttemptStatus::Berlangsung)
            ->chunkById(200, function ($batch) use ($attempts, $toleransi, &$ditutup) {
                foreach ($batch as $attempt) {
                    $attempts->finalisasiBilaKedaluwarsa($attempt, $toleransi);
                    $ditutup += $attempt->isBerlangsung() ? 0 : 1;
                }
            });

        $this->info("{$ditutup} attempt ditutup karena waktu habis.");

        return self::SUCCESS;
    }
}
