<?php

namespace App\Enums;

enum LogType: string
{
    // Pelanggaran yang masuk penghitung yang sama (PRD K-7).
    case PindahTab = 'pindah_tab';
    case KeluarFullscreen = 'keluar_fullscreen';
    // Insiden yang hanya dicatat untuk ditinjau dosen.
    case PerangkatBerganti = 'perangkat_berganti';

    public function label(): string
    {
        return match ($this) {
            self::PindahTab => 'Pindah tab/jendela',
            self::KeluarFullscreen => 'Keluar layar penuh',
            self::PerangkatBerganti => 'Perangkat berganti',
        };
    }

    public function dihitung(): bool
    {
        return match ($this) {
            self::PindahTab, self::KeluarFullscreen => true,
            self::PerangkatBerganti => false,
        };
    }

    /** @return list<self> */
    public static function pelanggaran(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->dihitung()));
    }
}
