<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['nim_nidn', 'nama', 'role', 'password', 'aktif'])]
#[Hidden(['password', 'remember_token', 'session_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'aktif' => 'boolean',
        ];
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** @return BelongsToMany<Kelas, $this> */
    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'class_students', 'user_id', 'class_id');
    }

    /** Ujian yang dibuat dosen ini. @return HasMany<Exam, $this> */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'dosen_id');
    }

    /** @return HasMany<ExamAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
