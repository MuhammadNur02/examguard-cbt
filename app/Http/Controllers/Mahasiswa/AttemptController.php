<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\FinishReason;
use App\Enums\LogType;
use App\Exceptions\UjianTidakTersedia;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\AttemptService;
use App\Services\ViolationService;
use App\Support\Perangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint attempt mahasiswa. Attempt selalu dicari dari pasangan
 * (ujian, pengguna login), sehingga mahasiswa tidak bisa membuka attempt orang lain.
 * Kegagalan UjianTidakTersedia dirender sebagai JSON (lihat bootstrap/app.php).
 */
class AttemptController extends Controller
{
    private const MAKS_PERCOBAAN_KODE = 5;

    private const JEDA_KODE_DETIK = 300;

    public function __construct(private readonly AttemptService $attempts) {}

    /** Mulai atau lanjutkan ujian. Persetujuan integritas wajib (FR-04.10). */
    public function start(Request $request, Exam $exam): JsonResponse|RedirectResponse
    {
        $this->pastikanTerlihat($request, $exam);
        $request->validate(
            ['setuju' => ['accepted']],
            ['setuju.accepted' => 'Centang persetujuan integritas sebelum memulai ujian.'],
        );

        try {
            $this->tolakPerangkatSeluler($request);

            // Kode akses hanya diperiksa saat attempt baru dibuat; melanjutkan tidak memintanya lagi.
            if (! $this->attempts->attemptMilik($exam, $request->user())) {
                $this->periksaKodeAkses($request, $exam);
            }

            $attempt = $this->attempts->mulai($exam, $request->user(), $request->ip(), $request->userAgent());
            if (! $attempt->wasRecentlyCreated) {
                $this->attempts->periksaPerangkat($attempt, $request->ip(), $request->userAgent());
            }
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
        $this->pastikanTerlihat($request, $exam);
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

    /** Ujian draf atau ujian untuk kelas lain tidak terlihat (FR-02.3, FR-02.6). */
    /**
     * FR-02.8: tanpa kode benar tidak ada attempt. Percobaan salah dibatasi per
     * mahasiswa per ujian agar kode tidak bisa ditebak beruntun.
     */
    private function periksaKodeAkses(Request $request, Exam $exam): void
    {
        if (! $exam->perluKodeAkses()) {
            return;
        }

        $kunci = 'kode-akses:'.$request->user()->id.':'.$exam->id;
        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN_KODE)) {
            $pesan = 'Terlalu banyak percobaan kode akses yang salah. Coba lagi dalam '.ceil(RateLimiter::availableIn($kunci) / 60).' menit.';
            if ($request->expectsJson()) {
                abort(429, $pesan);
            }
            throw ValidationException::withMessages(['kode_akses' => $pesan]);
        }

        $request->validate(
            ['kode_akses' => ['required', 'string', 'max:50']],
            ['kode_akses.required' => 'Masukkan kode akses dari dosen.'],
        );

        if (! $exam->kodeAksesCocok($request->input('kode_akses'))) {
            RateLimiter::hit($kunci, self::JEDA_KODE_DETIK);
            throw ValidationException::withMessages(['kode_akses' => 'Kode akses salah.']);
        }

        RateLimiter::clear($kunci);
    }

    private function pastikanTerlihat(Request $request, Exam $exam): void
    {
        abort_unless($exam->terlihatOleh($request->user()), 404);
    }

    private function attemptAktif(Request $request, Exam $exam)
    {
        $this->pastikanTerlihat($request, $exam);
        $attempt = $this->attempts->attemptAktif($exam, $request->user(), (int) config('examguard.toleransi_simpan_detik'));

        // Catat dulu perubahan perangkat (termasuk bila pindah ke ponsel), baru tolak ponsel.
        $this->attempts->periksaPerangkat($attempt, $request->ip(), $request->userAgent());
        $this->tolakPerangkatSeluler($request);

        return $attempt;
    }

    /**
     * FR-04.11: ujian hanya untuk laptop/komputer. Berdasarkan user-agent, jadi
     * dapat dikelabui (mis. "mode desktop"); tujuannya mencegah ketidaksengajaan.
     *
     * @throws UjianTidakTersedia
     */
    private function tolakPerangkatSeluler(Request $request): void
    {
        if (Perangkat::seluler($request->userAgent())) {
            throw new UjianTidakTersedia(AttemptService::PESAN_SELULER);
        }
    }

    /** @return array<string, mixed> */
    private function ringkasanUjian(Exam $exam): array
    {
        return ['ujian' => ['judul' => $exam->judul, 'mata_kuliah' => $exam->mata_kuliah]];
    }
}
