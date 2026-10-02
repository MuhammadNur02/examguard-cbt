<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['label', 'teks', 'is_correct', 'posisi_tetap'])]
#[Hidden(['is_correct'])]
class Option extends Model
{
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'posisi_tetap' => 'boolean',
        ];
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
