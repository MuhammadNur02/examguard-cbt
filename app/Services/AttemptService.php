<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use App\Enums\LogType;
use App\Exceptions\TindakanDitolak;
use App\Exceptions\UjianTidakTersedia;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Support\FisherYates;
use App\Support\Perangkat;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Siklus attempt mahasiswa: mulai/lanjut, susunan soal teracak, payload soal
 * tanpa kunci (FR-03.1–FR-03.4), autosave, heartbeat, dan finalisasi. Waktu dan
 * status selalu ditentukan server (PRD §9.1).
 */
class AttemptService
{
    /** @var Closure(): int */
    public const PESAN_SELULER = 'Gunakan laptop atau komputer untuk mengerjakan ujian. Perangkat seluler dan tablet tidak didukung.';

    public const PESAN_JARINGAN = 'Ujian ini hanya dapat dikerjakan dari jaringan kampus. Alamat IP Anda (:ip) tidak termasuk jaringan yang diizinkan.';

    /** FR-02.9: tolak permintaan dari IP di luar daftar jaringan ujian. @throws UjianTidakTersedia */
    public function pastikanJaringan(Exam $exam, ?string $ip): void
    {
        if (! $exam->jaringanDiizinkan($ip)) {
            throw new UjianTidakTersedia(str_replace(':ip', (string) $ip, self::PESAN_JARINGAN), 403, ['kode' => 'jaringan_ditolak']);
        }
    }

    private Closure $seedGenerator;

    /** @param  (Closure(): int)|null  $seedGenerator  dapat diganti di tes agar seed deterministik */
    public function __construct(?Closure $seedGenerator = null)
    {
        $this->seedGenerator = $seedGenerator ?? fn (): int => random_int(1, 2147483647);
    }

    /**
     * Mulai attempt baru atau lanjutkan attempt yang sedang berjalan.
     *
     * @throws UjianTidakTersedia
     */
    public function mulai(Exam $exam, User $mahasiswa, ?string $ip, ?string $userAgent): ExamAttempt
    {
        $attempt = $this->attemptMilik($exam, $mahasiswa);

        if ($attempt) {
            $this->finalisasiBilaKedaluwarsa($attempt);
            $this->pastikanBisaLanjut($attempt);

            return $attempt;
        }

        if (now()->lessThan($exam->mulai)) {
            throw new UjianTidakTersedia('Ujian belum dibuka. Ujian dimulai '.$exam->mulai->translatedFormat('d M Y H:i').' WIB.');
        }
        if (! $exam->dalamJadwal()) {
            throw new UjianTidakTersedia('Jadwal ujian sudah berakhir.');
        }

        $seed = ($this->seedGenerator)();
        [$urutanSoal, $urutanOpsi] = $this->susunUrutan($exam, $seed);

        try {
            $attempt = $exam->attempts()->create([
                'user_id' => $mahasiswa->id,
                'shuffle_seed' => $seed,
                'urutan_soal' => $urutanSoal,
                'urutan_opsi' => $urutanOpsi,
                'mulai' => now(),
                'status' => AttemptStatus::Berlangsung,
                'ip' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                'terakhir_aktif' => now(),
            ]);

            return $attempt->setRelation('exam', $exam);
        } catch (UniqueConstraintViolationException) {
            // Dua permintaan "mulai" bersamaan: pakai attempt yang sudah tercipta.
            return $this->attemptMilik($exam, $mahasiswa);
        }
    }

    /**
     * Keadaan ujian bagi seorang mahasiswa: akan_datang, dibuka, berlangsung,
     * selesai, atau ditutup (jadwal lewat tanpa attempt). Panggil setelah
     * finalisasiBilaKedaluwarsa agar attempt yang habis waktunya tidak terbaca berlangsung.
     */
    public function keadaan(Exam $exam, ?ExamAttempt $attempt): string
    {
        if ($attempt) {
            return $attempt->isBerlangsung() ? 'berlangsung' : 'selesai';
        }
        if (now()->lessThan($exam->mulai)) {
            return 'akan_datang';
        }

        return $exam->dalamJadwal() ? 'dibuka' : 'ditutup';
    }

    public function attemptMilik(Exam $exam, User $mahasiswa): ?ExamAttempt
    {
        return $exam->attempts()->where('user_id', $mahasiswa->id)->first()?->setRelation('exam', $exam);
    }

    /**
     * Attempt yang masih bisa dikerjakan, setelah finalisasi otomatis bila waktu habis.
     *
     * @throws UjianTidakTersedia 404 bila belum mulai, 409 bila sudah selesai/habis
     */
    public function attemptAktif(Exam $exam, User $mahasiswa, int $toleransiDetik = 0): ExamAttempt
    {
        $attempt = $this->attemptMilik($exam, $mahasiswa);
        if (! $attempt) {
            throw new UjianTidakTersedia('Anda belum memulai ujian ini.', 404);
        }

        $this->finalisasiBilaKedaluwarsa($attempt, $toleransiDetik);
        $this->pastikanBisaLanjut($attempt, $toleransiDetik);

        return $attempt;
    }

    /**
     * FR-06.6: tambah waktu untuk attempt yang masih berlangsung. Timer mahasiswa
     * menyesuaikan pada heartbeat berikutnya (sisa waktu selalu dari server).
     *
     * @throws TindakanDitolak
     */
    public function tambahWaktu(ExamAttempt $attempt, int $menit): CarbonInterface
    {
        $this->finalisasiBilaKedaluwarsa($attempt, (int) config('examguard.toleransi_simpan_detik'));

        // Bersyarat agar tidak berbalapan dengan penutupan otomatis/kirim.
        $berubah = ExamAttempt::whereKey($attempt->id)->where('status', AttemptStatus::Berlangsung)->increment('waktu_tambahan', $menit);
        if ($berubah !== 1) {
            throw new TindakanDitolak('Attempt ini sudah selesai. Gunakan "Buka ulang" untuk memberi waktu lagi.');
        }

        return $attempt->refresh()->batasWaktu();
    }

    /**
     * FR-06.6: buka ulang attempt yang sudah selesai/terkunci. Mahasiswa mendapat
     * sedikitnya $menit sejak sekarang, juga bila jadwal ujian sudah berakhir.
     * Ditolak bila pelanggaran masih melebihi batas (akan langsung terkunci lagi)
     * atau nilainya sudah dipublikasikan.
     *
     * @throws TindakanDitolak
     */
    public function bukaUlang(ExamAttempt $attempt, int $menit): CarbonInterface
    {
        $exam = $attempt->exam;
        if ($attempt->isBerlangsung()) {
            throw new TindakanDitolak('Attempt ini masih berlangsung. Gunakan "Tambah waktu" bila perlu.');
        }
        if ($attempt->result?->dipublikasikan_pada !== null) {
            throw new TindakanDitolak('Nilai peserta ini sudah dipublikasikan; attempt tidak dapat dibuka ulang.');
        }
        if ($attempt->jumlah_pelanggaran > $exam->batas_pelanggaran) {
            throw new TindakanDitolak("Pelanggaran peserta ini ({$attempt->jumlah_pelanggaran}) masih melebihi batas {$exam->batas_pelanggaran}. Maafkan pelanggaran terlebih dahulu.");
        }

        $perluMenit = (int) ceil($exam->selesaiPada()->diffInSeconds(now()->addMinutes($menit), false) / 60);
        $berubah = ExamAttempt::whereKey($attempt->id)->where('status', $attempt->status)->update([
            'status' => AttemptStatus::Berlangsung,
            'selesai' => null,
            'alasan_selesai' => null,
            'waktu_tambahan' => max($attempt->waktu_tambahan, $perluMenit),
        ]);
        if ($berubah !== 1) {
            throw new TindakanDitolak('Status attempt baru saja berubah. Muat ulang halaman lalu coba lagi.');
        }

        return $attempt->refresh()->batasWaktu();
    }

    /**
     * FR-04.9: catat insiden "perangkat berganti" bila IP atau peramban berbeda
     * dari permintaan sebelumnya pada attempt ini. Tidak menambah hitungan
     * pelanggaran (hanya ditinjau dosen; IP bisa berubah wajar saat ganti
     * jaringan). Pembaruan bersyarat mencegah permintaan paralel dari perangkat
     * baru mencatat perubahan yang sama dua kali.
     */
    public function periksaPerangkat(ExamAttempt $attempt, ?string $ip, ?string $userAgent): bool
    {
        $userAgent = $userAgent !== null ? mb_substr($userAgent, 0, 1000) : null;
        if (! $attempt->isBerlangsung() || ($attempt->ip === $ip && $attempt->user_agent === $userAgent)) {
            return false;
        }

        $lama = ['ip' => $attempt->ip, 'user_agent' => $attempt->user_agent];
        $berubah = ExamAttempt::whereKey($attempt->id)
            ->where(fn ($q) => $lama['ip'] === null ? $q->whereNull('ip') : $q->where('ip', $lama['ip']))
            ->where(fn ($q) => $lama['user_agent'] === null ? $q->whereNull('user_agent') : $q->where('user_agent', $lama['user_agent']))
            ->update(['ip' => $ip, 'user_agent' => $userAgent]);
        $attempt->forceFill(['ip' => $ip, 'user_agent' => $userAgent])->syncOriginal();

        // Attempt lama tanpa data perangkat: simpan saja sebagai acuan, bukan insiden.
        if ($berubah !== 1 || ($lama['ip'] === null && $lama['user_agent'] === null)) {
            return false;
        }

        $attempt->logs()->create([
            'jenis' => LogType::PerangkatBerganti,
            'waktu' => now(),
            'dihitung' => false,
            'detail' => [
                'ip_sebelumnya' => $lama['ip'],
                'ip_baru' => $ip,
                'perangkat_sebelumnya' => Perangkat::ringkas($lama['user_agent']),
                'perangkat_baru' => Perangkat::ringkas($userAgent),
                'ua_sebelumnya' => $lama['user_agent'] !== null ? mb_substr($lama['user_agent'], 0, 300) : null,
                'ua_baru' => $userAgent !== null ? mb_substr($userAgent, 0, 300) : null,
            ],
        ]);

        return true;
    }

    /** @throws UjianTidakTersedia */
    public function pastikanBisaLanjut(ExamAttempt $attempt, int $toleransiDetik = 0): void
    {
        if (! $attempt->isBerlangsung()) {
            throw new UjianTidakTersedia($this->pesanSelesai($attempt), 409, $this->statusUntukKlien($attempt));
        }
        if ($attempt->sisaDetik() + $toleransiDetik <= 0) {
            throw new UjianTidakTersedia('Waktu ujian sudah habis.', 409, $this->statusUntukKlien($attempt));
        }
    }

    public function pesanSelesai(ExamAttempt $attempt): string
    {
        return match ($attempt->alasan_selesai) {
            FinishReason::Pelanggaran => 'Ujian Anda dikunci karena batas pelanggaran terlampaui. Jawaban yang tersimpan sudah dikirim.',
            FinishReason::DikunciDosen => 'Ujian Anda dikunci oleh dosen. Jawaban yang tersimpan sudah dikirim.',
            FinishReason::WaktuHabis => 'Waktu ujian sudah habis. Jawaban yang tersimpan sudah dikirim.',
            default => 'Ujian ini sudah Anda selesaikan.',
        };
    }

    /** Tutup attempt yang melewati batas waktu (+ toleransi) sebagai "waktu habis". */
    public function finalisasiBilaKedaluwarsa(ExamAttempt $attempt, int $toleransiDetik = 0): void
    {
        if ($attempt->isBerlangsung() && now()->greaterThan($attempt->batasWaktu()->copy()->addSeconds($toleransiDetik))) {
            $this->selesaikan($attempt, FinishReason::WaktuHabis);
        }
    }

    /**
     * Finalisasi attempt satu kali saja (aman dari permintaan bersamaan).
     * Jawaban yang sudah tersimpan di server menjadi jawaban yang dikirim.
     *
     * @return bool true bila attempt baru saja difinalisasi oleh panggilan ini
     */
    public function selesaikan(ExamAttempt $attempt, FinishReason $alasan): bool
    {
        $status = in_array($alasan, [FinishReason::Pelanggaran, FinishReason::DikunciDosen], true)
            ? AttemptStatus::Terkunci
            : AttemptStatus::Selesai;

        $berubah = ExamAttempt::whereKey($attempt->id)
            ->where('status', AttemptStatus::Berlangsung)
            ->update(['status' => $status, 'alasan_selesai' => $alasan, 'selesai' => now(), 'updated_at' => now()]);

        $attempt->refresh();

        if ($berubah === 1) {
            // Nilai PG instan di backend (FR-05.1); esai menunggu skor rekomendasi + dosen.
            app(ScoringService::class)->nilaiOtomatis($attempt);
        }

        return $berubah === 1;
    }

    /**
     * Simpan jawaban berdasarkan posisi tampil. Item: nomor (1..n), opsi
     * (indeks tampil, null = kosongkan) untuk PG, teks untuk esai, ragu.
     *
     * @param  list<array<string, mixed>>  $items
     *
     * @throws ValidationException bila nomor/opsi tidak sesuai susunan attempt
     */
    public function simpanJawaban(ExamAttempt $attempt, array $items): CarbonInterface
    {
        $waktu = now();
        $questions = Question::whereIn('id', $attempt->urutan_soal)->get(['id', 'tipe'])->keyBy('id');

        DB::transaction(function () use ($attempt, $items, $questions, $waktu) {
            $tersimpan = $attempt->answers()->get()->keyBy('question_id');

            foreach ($items as $item) {
                $nomor = (int) $item['nomor'];
                $peta = $this->petakanPosisi($attempt, $nomor);
                if (! $peta) {
                    throw ValidationException::withMessages(['jawaban' => "Nomor soal {$nomor} tidak valid."]);
                }

                $question = $questions[$peta['question_id']];
                $data = ['disimpan_pada' => $waktu];

                if (array_key_exists('ragu', $item)) {
                    $data['ragu'] = (bool) $item['ragu'];
                }

                if ($question->isPg() && array_key_exists('opsi', $item)) {
                    if ($item['opsi'] === null) {
                        $data['option_id'] = null;
                    } else {
                        $petaOpsi = $this->petakanPosisi($attempt, $nomor, (int) $item['opsi']);
                        if (! $petaOpsi) {
                            throw ValidationException::withMessages(['jawaban' => "Pilihan pada soal {$nomor} tidak valid."]);
                        }
                        $data['option_id'] = $petaOpsi['option_id'];
                    }
                }

                if (! $question->isPg() && array_key_exists('teks', $item)) {
                    $data['teks_jawaban'] = $item['teks'];

                    // Teks berubah setelah dinilai (hanya mungkin setelah attempt dibuka ulang):
                    // skor lama tidak lagi sesuai jawaban dan harus dihitung/dikoreksi ulang.
                    $lama = $tersimpan->get($question->id);
                    if ($lama && $lama->teks_jawaban !== $item['teks'] && ($lama->skor_sistem !== null || $lama->skor_final !== null)) {
                        $data += [
                            'similarity' => null, 'skor_sistem' => null, 'kata_kunci_cocok' => null,
                            'skor_final' => null, 'dinilai_oleh' => null, 'dinilai_pada' => null,
                        ];
                    }
                }

                $attempt->answers()->updateOrCreate(['question_id' => $question->id], $data);
            }

            $attempt->forceFill(['terakhir_aktif' => $waktu])->save();
        });

        return $waktu;
    }

    /** Tandai peserta masih aktif (FR-04.7). */
    public function heartbeat(ExamAttempt $attempt): void
    {
        $attempt->forceFill(['terakhir_aktif' => now()])->save();
    }

    /**
     * Status ringkas untuk layar ujian. Waktu dihitung server.
     *
     * @return array<string, mixed>
     */
    public function statusUntukKlien(ExamAttempt $attempt): array
    {
        return [
            'attempt' => [
                'status' => $attempt->status->value,
                'alasan_selesai' => $attempt->alasan_selesai?->value,
                'sisa_detik' => $attempt->isBerlangsung() ? $attempt->sisaDetik() : 0,
                'jumlah_pelanggaran' => $attempt->jumlah_pelanggaran,
                'batas_pelanggaran' => $attempt->exam->batas_pelanggaran,
                'jumlah_soal' => count($attempt->urutan_soal),
            ],
            'waktu_server' => now()->toIso8601String(),
        ];
    }

    /**
     * Susun urutan soal dan opsi dengan satu aliran PRNG berseed: soal diacak
     * lebih dulu (bila acak_soal), lalu opsi tiap soal PG mengikuti urutan soal
     * hasil acak (bila acak_opsi). Seed yang sama selalu menghasilkan susunan sama.
     *
     * @return array{0: list<int>, 1: array<int, list<int>>}
     */
    public function susunUrutan(Exam $exam, int $seed): array
    {
        $rng = new Randomizer(new Mt19937($seed));
        $questions = $exam->questions()->with('options')->get()->keyBy('id');

        $urutanSoal = $questions->keys()->all();
        $pool = $exam->pool_size;
        if ($pool !== null && $pool > 0 && $pool < count($urutanSoal)) {
            // FR-03.6: subset acak N dari M; tanpa acak soal, subset tetap urut seperti aslinya.
            $terpilih = array_slice(FisherYates::shuffle($urutanSoal, $rng), 0, $pool);
            $urutanSoal = $exam->acak_soal ? $terpilih : array_values(array_intersect($urutanSoal, $terpilih));
        } elseif ($exam->acak_soal) {
            $urutanSoal = FisherYates::shuffle($urutanSoal, $rng);
        }

        $urutanOpsi = [];
        foreach ($urutanSoal as $questionId) {
            $question = $questions[$questionId];
            if (! $question->isPg()) {
                continue;
            }

            $opsi = $question->options; // berurutan menurut label A-E
            $urutanOpsi[$questionId] = $exam->acak_opsi
                ? FisherYates::shuffleExceptLocked($opsi->pluck('id')->all(), $opsi->pluck('posisi_tetap')->all(), $rng)
                : $opsi->pluck('id')->all();
        }

        return [$urutanSoal, $urutanOpsi];
    }

    /**
     * Payload soal untuk mahasiswa. Dibangun eksplisit: hanya nomor tampil,
     * tipe, teks, bobot, teks opsi berhuruf tampil, dan jawaban tersimpan.
     * Tidak memuat ID asli, label asli, is_correct, kunci esai, atau kata kunci.
     *
     * @return list<array<string, mixed>>
     */
    public function soalUntukKlien(ExamAttempt $attempt): array
    {
        $questions = Question::with('options')->whereIn('id', $attempt->urutan_soal)->get()->keyBy('id');
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $payload = [];
        foreach ($attempt->urutan_soal as $indeks => $questionId) {
            $question = $questions->get($questionId);
            if (! $question) {
                continue;
            }

            $jawaban = $answers->get($questionId);
            $item = [
                'nomor' => $indeks + 1,
                'tipe' => $question->tipe->value,
                'teks' => $question->teks,
                'bobot' => $question->bobot,
            ];

            if ($question->isPg()) {
                $urutanOpsi = $attempt->urutan_opsi[$questionId] ?? [];
                $opsi = $question->options->keyBy('id');
                $item['opsi'] = array_values(array_map(
                    fn (int $optionId, int $posisi) => ['huruf' => chr(65 + $posisi), 'teks' => $opsi[$optionId]->teks],
                    $urutanOpsi,
                    array_keys($urutanOpsi),
                ));
                $posisiDipilih = $jawaban?->option_id ? array_search($jawaban->option_id, $urutanOpsi, true) : false;
                $item['jawaban'] = $posisiDipilih === false ? null : $posisiDipilih;
            } else {
                $item['jawaban'] = $jawaban?->teks_jawaban;
            }

            $item['ragu'] = (bool) $jawaban?->ragu;
            $payload[] = $item;
        }

        return $payload;
    }

    /**
     * Petakan posisi tampil (nomor soal 1..n, indeks opsi 0..k-1) ke ID asli
     * memakai pemetaan tersimpan di attempt. Null bila posisi tidak valid.
     *
     * @return array{question_id: int, option_id: int|null}|null
     */
    public function petakanPosisi(ExamAttempt $attempt, int $nomor, ?int $indeksOpsi = null): ?array
    {
        $questionId = $nomor >= 1 ? ($attempt->urutan_soal[$nomor - 1] ?? null) : null;
        if ($questionId === null) {
            return null;
        }

        if ($indeksOpsi === null) {
            return ['question_id' => $questionId, 'option_id' => null];
        }

        $optionId = $indeksOpsi >= 0 ? ($attempt->urutan_opsi[$questionId][$indeksOpsi] ?? null) : null;

        return $optionId !== null ? ['question_id' => $questionId, 'option_id' => $optionId] : null;
    }
}
