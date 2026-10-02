<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'skor_pg', 'skor_esai_sistem', 'skor_esai_final', 'skor_maksimal',
    'nilai_akhir', 'dipublikasikan_pada',
])]
class ExamResult extends Model
{
    protected function casts(): array
    {
        return [
            'skor_pg' => 'float',
            'skor_esai_sistem' => 'float',
            'skor_esai_final' => 'float',
            'skor_maksimal' => 'float',
            'nilai_akhir' => 'float',
            'dipublikasikan_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<ExamAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }
}
