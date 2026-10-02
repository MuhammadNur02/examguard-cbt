<?php

namespace App\Enums;

enum QuestionType: string
{
    case Pg = 'pg';
    case Esai = 'esai';

    public function label(): string
    {
        return match ($this) {
            self::Pg => 'Pilihan Ganda',
            self::Esai => 'Esai',
        };
    }
}
