<?php

namespace App\Models;

use App\Enums\QuestionType;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['urutan', 'tipe', 'teks', 'bobot', 'kunci_esai', 'keywords'])]
#[Hidden(['kunci_esai', 'keywords'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'tipe' => QuestionType::class,
            'bobot' => 'float',
            'keywords' => 'array',
        ];
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return HasMany<Option, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('label');
    }

    /** @return HasMany<StudentAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function isPg(): bool
    {
        return $this->tipe === QuestionType::Pg;
    }
}
