<?php

namespace App\Models;

use App\Enums\LogType;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Milidetik disimpan agar debounce dan latensi pencatatan (< 1 detik) terukur.
#[DateFormat('Y-m-d H:i:s.v')]
#[Fillable([
    'jenis', 'waktu', 'detail', 'dihitung', 'dimaafkan', 'dimaafkan_oleh',
    'dimaafkan_pada', 'alasan',
])]
class ExamLog extends Model
{
    protected function casts(): array
    {
        return [
            'jenis' => LogType::class,
            'waktu' => 'datetime',
            'detail' => 'array',
            'dihitung' => 'boolean',
            'dimaafkan' => 'boolean',
            'dimaafkan_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<ExamAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pemaaf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dimaafkan_oleh');
    }
}
