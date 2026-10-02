<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Exceptions\UjianTidakTersedia;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint attempt mahasiswa. Attempt selalu dicari dari pasangan
 * (ujian, pengguna login), sehingga mahasiswa tidak bisa membuka attempt orang lain.
 */
class AttemptController extends Controller
{
    public function __construct(private readonly AttemptService $attempts) {}

    /** Mulai atau lanjutkan ujian. Persetujuan integritas wajib (FR-04.10). */
    public function start(Request $request, Exam $exam): JsonResponse
    {
        $this->pastikanTerlihat($exam);
        $request->validate(
            ['setuju' => ['accepted']],
            ['setuju.accepted' => 'Centang persetujuan integritas sebelum memulai ujian.'],
        );

        try {
            $attempt = $this->attempts->mulai($exam, $request->user(), $request->ip(), $request->userAgent());
        } catch (UjianTidakTersedia $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json($this->ringkasan($attempt), $attempt->wasRecentlyCreated ? 201 : 200);
    }

    /** Soal teracak milik mahasiswa ini, tanpa kunci jawaban (FR-03.4). */
    public function questions(Request $request, Exam $exam): JsonResponse
    {
        $this->pastikanTerlihat($exam);
        $attempt = $this->attempts->attemptMilik($exam, $request->user());

        if (! $attempt) {
            return response()->json(['message' => 'Anda belum memulai ujian ini.'], 404);
        }

        try {
            $this->attempts->pastikanBisaLanjut($attempt);
        } catch (UjianTidakTersedia $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json([...$this->ringkasan($attempt), 'soal' => $this->attempts->soalUntukKlien($attempt)]);
    }

    /** Ujian draf tidak terlihat oleh mahasiswa (FR-02.3). */
    private function pastikanTerlihat(Exam $exam): void
    {
        abort_unless($exam->isPublished(), 404);
    }

    /** @return array<string, mixed> */
    private function ringkasan(ExamAttempt $attempt): array
    {
        return [
            'ujian' => [
                'judul' => $attempt->exam->judul,
                'mata_kuliah' => $attempt->exam->mata_kuliah,
                'batas_pelanggaran' => $attempt->exam->batas_pelanggaran,
            ],
            'attempt' => [
                'status' => $attempt->status->value,
                'sisa_detik' => $attempt->sisaDetik(),
                'jumlah_pelanggaran' => $attempt->jumlah_pelanggaran,
                'jumlah_soal' => count($attempt->urutan_soal),
            ],
            'waktu_server' => now()->toIso8601String(),
        ];
    }
}
