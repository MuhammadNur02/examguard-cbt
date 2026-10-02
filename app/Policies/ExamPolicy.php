<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    /** Hanya dosen pembuat ujian yang dapat mengelolanya (K-5). */
    public function kelola(User $user, Exam $exam): bool
    {
        return $user->role === Role::Dosen && $exam->dosen_id === $user->id;
    }
}
