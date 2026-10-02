<?php

namespace App\Models;

use Database\Factories\KelasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Table(name: 'classes')]
#[Fillable(['nama', 'keterangan'])]
class Kelas extends Model
{
    /** @use HasFactory<KelasFactory> */
    use HasFactory;

    /** @return BelongsToMany<User, $this> */
    public function mahasiswa(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_students', 'class_id', 'user_id');
    }

    /** @return BelongsToMany<Exam, $this> */
    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_classes', 'class_id', 'exam_id');
    }
}
