<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\FinishReason;
use App\Enums\LogType;
use App\Exceptions\UjianTidakTersedia;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\AttemptService;
use App\Services\ViolationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endpoint attempt mahasiswa. Attempt selalu dicari dari pasangan
 * (ujian, pengguna login), sehingga mahasiswa tidak bisa membuka attempt orang lain.
 * Kegagalan UjianTidakTersedia dirender sebagai JSON (lihat bootstrap/app.php).
 */
class AttemptController extends Controller
{
    public function __construct(private readonly AttemptService $attempts) {}

    /** Mulai atau lanjutkan ujian. Persetujuan integritas wajib (FR-04.10). */
    public function start(Request $request, Exam $exam): JsonResponse|RedirectResponse
    {
        $this->pastikanTerlihat($exam);
        $request->validate(
            ['setuju' => ['accepted']],
            ['setuju.accepted' => 'Centang persetujuan integritas sebelum memulai ujian.'],
        );

        try {
            $attempt = $this->attempts->mulai($exam, $request->user(), $request->ip(), $request->userAgent());
        } catch (UjianTidakTersedia $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return redirect()->route('mahasiswa.exams.show', $exam)->with('error', $e->getMessage());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('mahasiswa.exams.work', $exam);
        }

        return response()->json(
            [...$this->ringkasanUjian($exam), ...$this->attempts->statusUntukKlien($attempt)],
            $attempt->wasRecentlyCreated ? 201 : 200,
        );
    }

    /** Soal teracak milik mahasiswa ini, tanpa kunci jawaban (FR-03.4). */
    public function questions(Request $request, Exam $exam): JsonResponse
    {
        $attempt = $this->attemptAktif($request, $exam);

        return response()->json([
            ...$this->ringkasanUjian($exam),
            ...$this->attempts->statusUntukKlien($attempt),
            'soal' => $this->attempts->soalUntukKlien($attempt),
        ]);
    }

    /** Autosave satu atau beberapa jawaban (FR-04.7). */
    public function saveAnswers(Request $request, Exam $exam): JsonResponse
    {
        $attempt = $this->attemptAktif($request, $exam);
        $data = $request->validate([
            'jawaban' => ['required', 'array', 'min:1', 'max:100'],
            'jawaban.*.nomor' => ['required', 'integer', 'min:1'],
            'jawaban.*.opsi' => ['nullable', 'integer', 'min:0'],
            'jawaban.*.teks' => ['nullable', 'string', 'max:20000'],
            'jawaban.*.ragu' => ['sometimes', 'boolean'],
        ]);

        $waktu = $this->attempts->simpanJawaban($attempt, $data['jawaban']);

        return response()->json([
            ...$this->attempts->statusUntukKlien($attempt),
            'tersimpan_pada' => $waktu->toIso8601String(),
        ]);
    }

    /** Sinyal hidup berkala; mengembalikan sisa waktu dan status dari server. */
    public function heartbeat(Request $request, Exam $exam): JsonResponse
    {
        $attempt = $this->attemptAktif($request, $exam);
        $this->attempts->heartbeat($attempt);

        return response()->json($this->attempts->statusUntukKlien($attempt));
    }

    /** Laporan pindah tab / keluar layar penuh dari peramban (FR-04.1, FR-04.2). */
    public function violation(Request $request, Exam $exam, ViolationService $violations): JsonResponse
    {
        $attempt = $this->attemptAktif($request, $exam);
        $data = $request->validate([
            'jenis' => ['required', Rule::in([LogType::PindahTab->value, LogType::KeluarFullscreen->value])],
            'pemicu' => ['nullable', Rule::in(['visibilitychange', 'blur', 'fullscreenchange'])],
            'waktu_klien' => ['nullable', 'date', 'max:40'],
        ]);

        $hasil = $violations->catat($attempt, LogType::from($data['jenis']), array_filter([
            'pemicu' => $data['pemicu'] ?? null,
            'waktu_klien' => $data['waktu_klien'] ?? null,
        ]));

        return response()->json([...$this->attempts->statusUntukKlien($attempt), ...$hasil]);
    }

    /** Kirim jawaban dan finalkan attempt (FR-07.2). Idempoten. */
    public function submit(Request $request, Exam $exam): JsonResponse|RedirectResponse
    {
        $this->pastikanTerlihat($exam);
        $attempt = $this->attempts->attemptMilik($exam, $request->user());
        abort_unless($attempt, 404);

        if ($attempt->isBerlangsung()) {
            $alasan = now()->greaterThanOrEqualTo($attempt->batasWaktu()) ? FinishReason::WaktuHabis : FinishReason::Manual;
            $this->attempts->selesaikan($attempt, $alasan);
        }

        if ($request->expectsJson()) {
            return response()->json($this->attempts->statusUntukKlien($attempt));
        }

        return redirect()->route('mahasiswa.exams.show', $exam)->with('status', 'Jawaban Anda telah dikirim.');
    }

    /** Ujian draf tidak terlihat oleh mahasiswa (FR-02.3). */
    private function pastikanTerlihat(Exam $exam): void
    {
        abort_unless($exam->isPublished(), 404);
    }

    private function attemptAktif(Request $request, Exam $exam)
    {
        $this->pastikanTerlihat($exam);

        return $this->attempts->attemptAktif($exam, $request->user(), (int) config('examguard.toleransi_simpan_detik'));
    }

    /** @return array<string, mixed> */
    private function ringkasanUjian(Exam $exam): array
    {
        return ['ujian' => ['judul' => $exam->judul, 'mata_kuliah' => $exam->mata_kuliah]];
    }
}
