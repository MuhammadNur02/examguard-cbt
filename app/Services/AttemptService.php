<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Exceptions\UjianTidakTersedia;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Support\FisherYates;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Siklus attempt mahasiswa: mulai/lanjut, susunan soal teracak, dan payload
 * soal untuk klien yang tidak pernah memuat kunci jawaban (FR-03.1–FR-03.4).
 */
class AttemptService
{
    /** @var Closure(): int */
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

    public function attemptMilik(Exam $exam, User $mahasiswa): ?ExamAttempt
    {
        return $exam->attempts()->where('user_id', $mahasiswa->id)->first()?->setRelation('exam', $exam);
    }

    /** @throws UjianTidakTersedia */
    public function pastikanBisaLanjut(ExamAttempt $attempt): void
    {
        if (! $attempt->isBerlangsung()) {
            throw new UjianTidakTersedia('Ujian ini sudah Anda selesaikan.', 409);
        }
        if ($attempt->sisaDetik() <= 0) {
            throw new UjianTidakTersedia('Waktu ujian sudah habis.', 409);
        }
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
        if ($exam->acak_soal) {
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
        $questionId = $attempt->urutan_soal[$nomor - 1] ?? null;
        if ($nomor < 1 || $questionId === null) {
            return null;
        }

        if ($indeksOpsi === null) {
            return ['question_id' => $questionId, 'option_id' => null];
        }

        $optionId = $attempt->urutan_opsi[$questionId][$indeksOpsi] ?? null;

        return $indeksOpsi >= 0 && $optionId !== null
            ? ['question_id' => $questionId, 'option_id' => $optionId]
            : null;
    }
}
