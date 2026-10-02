<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case Berlangsung = 'berlangsung';
    case Selesai = 'selesai';
    // Dikirim paksa karena batas pelanggaran terlampaui atau dikunci dosen.
    case Terkunci = 'terkunci';

    public function label(): string
    {
        return match ($this) {
            self::Berlangsung => 'Berlangsung',
            self::Selesai => 'Selesai',
            self::Terkunci => 'Terkunci',
        };
    }
}
