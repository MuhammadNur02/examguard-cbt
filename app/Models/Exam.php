<?php

namespace App\Models;

use App\Enums\ExamStatus;
use Carbon\CarbonInterface;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'judul', 'mata_kuliah', 'mulai', 'durasi_menit', 'batas_pelanggaran',
    'acak_soal', 'acak_opsi', 'pool_size', 'status',
])]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'mulai' => 'datetime',
            'durasi_menit' => 'integer',
            'batas_pelanggaran' => 'integer',
            'acak_soal' => 'boolean',
            'acak_opsi' => 'boolean',
            'pool_size' => 'integer',
            'status' => ExamStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function dosen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    /** @return HasOne<ExamAccess, $this> */
    public function access(): HasOne
    {
        return $this->hasOne(ExamAccess::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('urutan')->orderBy('id');
    }

    /** @return HasMany<ExamAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /** @return BelongsToMany<Kelas, $this> */
    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'exam_classes', 'exam_id', 'class_id');
    }

    public function isPublished(): bool
    {
        return $this->status === ExamStatus::Published;
    }

    /**
     * Ujian terlihat oleh mahasiswa bila terbit dan (tidak ditetapkan ke kelas
     * mana pun, atau mahasiswa anggota salah satu kelas yang ditetapkan) (FR-02.6).
     */
    public function terlihatOleh(User $mahasiswa): bool
    {
        if (! $this->isPublished()) {
            return false;
        }

        $kelas = $this->kelas()->pluck('classes.id');

        return $kelas->isEmpty()
            || $mahasiswa->kelas()->whereIn('classes.id', $kelas)->exists();
    }

    /** @param  Builder<Exam>  $query */
    #[Scope]
    protected function terlihatUntuk(Builder $query, User $mahasiswa): void
    {
        $query->where('status', ExamStatus::Published)
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('kelas')
                ->orWhereHas('kelas.mahasiswa', fn (Builder $m) => $m->whereKey($mahasiswa->id)));
    }

    /** Akhir jendela ujian: mulai + durasi. */
    public function selesaiPada(): CarbonInterface
    {
        return $this->mulai->copy()->addMinutes($this->durasi_menit);
    }

    /** Ujian berada di dalam jadwal [mulai, selesai). */
    public function dalamJadwal(?CarbonInterface $waktu = null): bool
    {
        $waktu ??= now();

        return $waktu->greaterThanOrEqualTo($this->mulai) && $waktu->lessThan($this->selesaiPada());
    }

    /** Sudah ada mahasiswa yang memulai; ujian tidak boleh diubah lagi. */
    public function sudahDikerjakan(): bool
    {
        return $this->attempts()->exists();
    }

    /**
     * Alasan ujian belum dapat diterbitkan (FR-02.2). Kosong berarti siap.
     *
     * @return list<string>
     */
    public function masalahPublikasi(): array
    {
        $questions = $this->questions()->with('options')->get();

        if ($questions->isEmpty()) {
            return ['Ujian belum memiliki soal.'];
        }

        $masalah = [];
        foreach ($questions->values() as $i => $question) {
            $nomor = $i + 1;
            if ($question->isPg()) {
                if ($question->options->count() < 2) {
                    $masalah[] = "Soal {$nomor}: minimal dua opsi.";
                }
                if ($question->options->where('is_correct', true)->count() !== 1) {
                    $masalah[] = "Soal {$nomor}: harus memiliki tepat satu kunci jawaban.";
                }
            } elseif (blank($question->kunci_esai)) {
                $masalah[] = "Soal {$nomor}: kunci esai wajib diisi.";
            }
        }

        return $masalah;
    }
}
