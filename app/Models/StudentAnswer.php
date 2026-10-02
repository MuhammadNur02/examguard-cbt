<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'question_id', 'option_id', 'teks_jawaban', 'ragu', 'disimpan_pada',
    'similarity', 'kata_kunci_cocok', 'skor_sistem', 'skor_final', 'dinilai_oleh', 'dinilai_pada',
])]
class StudentAnswer extends Model
{
    protected function casts(): array
    {
        return [
            'ragu' => 'boolean',
            'disimpan_pada' => 'datetime',
            'similarity' => 'float',
            'kata_kunci_cocok' => 'array',
            'skor_sistem' => 'float',
            'skor_final' => 'float',
            'dinilai_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<ExamAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<Option, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }

    /** @return BelongsTo<User, $this> */
    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dinilai_oleh');
    }
}
