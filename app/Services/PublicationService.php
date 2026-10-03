<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamResult;
use Carbon\CarbonInterface;

/**
 * Publikasi nilai (FR-07.3) dan publikasi terjadwal (FR-08.1). Hanya nilai
 * yang sudah final yang dipublikasikan; sisanya menunggu publikasi berikutnya.
 */
class PublicationService
{
    public function __construct(private readonly ReportService $laporan) {}

    /** @return array{jumlah: int, belum_final: int} */
    public function terbitkan(Exam $exam, CarbonInterface $waktu): array
    {
        $baris = $this->laporan->baris($exam);
        $siap = $baris->where('final', true)->where('dipublikasikan', false);

        ExamResult::whereIn('attempt_id', $siap->pluck('attempt.id'))->update(['dipublikasikan_pada' => $waktu]);

        return ['jumlah' => $siap->count(), 'belum_final' => $baris->where('final', false)->count()];
    }

    /**
     * Jalankan jadwal yang sudah jatuh tempo. Dipanggil penjadwal tiap menit dan
     * saat halaman nilai dibuka, sehingga nilai muncul tepat waktu walau
     * penjadwal terlambat. Waktu publikasi = waktu yang dijadwalkan.
     */
    public function jalankanTerjadwal(): int
    {
        $dijalankan = 0;

        foreach (Exam::whereNotNull('nilai_terbit_pada')->where('nilai_terbit_pada', '<=', now())->get() as $exam) {
            $waktu = $exam->nilai_terbit_pada;
            // Klaim bersyarat: jadwal hanya dijalankan sekali walau dipicu bersamaan.
            if (Exam::whereKey($exam->id)->where('nilai_terbit_pada', $waktu)->update(['nilai_terbit_pada' => null]) !== 1) {
                continue;
            }

            $hasil = $this->terbitkan($exam, $waktu);
            AuditLog::catatSistem('nilai_dipublikasikan', $exam, [...$hasil, 'terjadwal' => true, 'waktu' => $waktu->toIso8601String()]);
            $dijalankan++;
        }

        return $dijalankan;
    }
}
