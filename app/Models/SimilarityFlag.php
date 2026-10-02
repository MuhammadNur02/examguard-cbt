<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['question_id', 'attempt_a', 'attempt_b', 'skor'])]
class SimilarityFlag extends Model
{
    protected function casts(): array
    {
        return ['skor' => 'float'];
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<ExamAttempt, $this> */
    public function attemptA(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_a');
    }

    /** @return BelongsTo<ExamAttempt, $this> */
    public function attemptB(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_b');
    }
}
