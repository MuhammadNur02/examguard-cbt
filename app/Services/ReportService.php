<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Rekap nilai per ujian (FR-09.1). Satu sumber data untuk tabel dan ekspor
 * Excel agar isi berkas sama dengan tampilan (FR-09.2).
 */
class ReportService
{
    public function __construct(
        private readonly AttemptService $attempts,
        private readonly ScoringService $scoring,
    ) {}

    /**
     * @return Collection<int, array{attempt: ExamAttempt, nim: string, nama: string, status: string,
     *     skor_pg: ?float, skor_esai: ?float, skor_maksimal: ?float, nilai_akhir: ?float, final: bool,
     *     keterangan: string, pelanggaran: int, dikirim: ?CarbonInterface, dipublikasikan: bool}>
     */
    public function baris(Exam $exam): Collection
    {
        $toleransi = (int) config('examguard.toleransi_simpan_detik');
        $exam->attempts()->where('status', AttemptStatus::Berlangsung)->get()
            ->each(fn (ExamAttempt $a) => $this->attempts->finalisasiBilaKedaluwarsa($a->setRelation('exam', $exam), $toleransi));

        // Attempt final yang belum punya rekap (mis. data lama) dinilai dulu.
        $exam->attempts()->where('status', '!=', AttemptStatus::Berlangsung)->doesntHave('result')->get()
            ->each(fn (ExamAttempt $a) => $this->scoring->nilaiOtomatis($a));

        return $exam->attempts()
            ->with(['user:id,nim_nidn,nama', 'result'])
            ->get()
            ->sortBy(fn (ExamAttempt $a) => $a->user->nim_nidn)
            ->values()
            ->map(function (ExamAttempt $attempt) {
                $hasil = $attempt->result;
                $berlangsung = $attempt->isBerlangsung();
                $final = ! $berlangsung && $hasil?->nilai_akhir !== null;

                return [
                    'attempt' => $attempt,
                    'nim' => $attempt->user->nim_nidn,
                    'nama' => $attempt->user->nama,
                    'status' => $attempt->status->label(),
                    'skor_pg' => $berlangsung ? null : $hasil?->skor_pg,
                    'skor_esai' => $berlangsung ? null : $hasil?->skor_esai_final,
                    'skor_maksimal' => $hasil?->skor_maksimal,
                    'nilai_akhir' => $final ? $hasil->nilai_akhir : null,
                    'final' => $final,
                    'keterangan' => match (true) {
                        $berlangsung => 'Sedang mengerjakan',
                        $final => 'Final',
                        default => 'Menunggu koreksi esai',
                    },
                    'pelanggaran' => $attempt->jumlah_pelanggaran,
                    'dikirim' => $attempt->selesai,
                    'dipublikasikan' => (bool) $hasil?->dipublikasikan_pada?->lessThanOrEqualTo(now()),
                ];
            });
    }
}
