<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Terbit',
        };
    }
}
