<?php

namespace App\Enums;

enum FinishReason: string
{
    case Manual = 'manual';
    case WaktuHabis = 'waktu_habis';
    case Pelanggaran = 'pelanggaran';
    case DikunciDosen = 'dikunci_dosen';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Dikirim mahasiswa',
            self::WaktuHabis => 'Waktu habis',
            self::Pelanggaran => 'Batas pelanggaran terlampaui',
            self::DikunciDosen => 'Dikunci dosen',
        };
    }
}
