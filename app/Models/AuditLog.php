<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'aksi', 'subjek_tipe', 'subjek_id', 'detail', 'ip'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['detail' => 'array'];
    }

    /** Catat satu aksi. Pelaku dan IP diambil dari permintaan aktif bila tidak diberikan. */
    public static function catat(string $aksi, ?Model $subjek = null, array $detail = [], ?User $pelaku = null): self
    {
        $pelaku ??= auth()->user();

        return self::create([
            'user_id' => $pelaku?->getKey(),
            'aksi' => $aksi,
            'subjek_tipe' => $subjek ? class_basename($subjek) : null,
            'subjek_id' => $subjek?->getKey(),
            'detail' => $detail ?: null,
            'ip' => request()->ip(),
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
