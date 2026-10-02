<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\FinishReason;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id', 'shuffle_seed', 'urutan_soal', 'urutan_opsi', 'mulai', 'selesai',
    'status', 'alasan_selesai', 'jumlah_pelanggaran', 'waktu_tambahan', 'ip',
    'user_agent', 'terakhir_aktif',
])]
class ExamAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'shuffle_seed' => 'integer',
            'urutan_soal' => 'array',
            'urutan_opsi' => 'array',
            'mulai' => 'datetime',
            'selesai' => 'datetime',
            'status' => AttemptStatus::class,
            'alasan_selesai' => FinishReason::class,
            'jumlah_pelanggaran' => 'integer',
            'waktu_tambahan' => 'integer',
            'terakhir_aktif' => 'datetime',
        ];
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StudentAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'attempt_id');
    }

    /** @return HasMany<ExamLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(ExamLog::class, 'attempt_id');
    }

    /** @return HasOne<ExamResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(ExamResult::class, 'attempt_id');
    }

    public function isBerlangsung(): bool
    {
        return $this->status === AttemptStatus::Berlangsung;
    }

    /** Batas waktu attempt: akhir jendela ujian + waktu tambahan (FR-06.6). */
    public function batasWaktu(): CarbonInterface
    {
        return $this->exam->selesaiPada()->copy()->addMinutes($this->waktu_tambahan);
    }

    public function sisaDetik(?CarbonInterface $waktu = null): int
    {
        $waktu ??= now();

        return max(0, (int) floor($waktu->diffInSeconds($this->batasWaktu(), false)));
    }
}
