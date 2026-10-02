<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Dosen = 'dosen';
    case Mahasiswa = 'mahasiswa';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Dosen => 'Dosen',
            self::Mahasiswa => 'Mahasiswa',
        };
    }

    /** Nama identitas login untuk peran ini (FR-01.1). */
    public function identitas(): string
    {
        return match ($this) {
            self::Admin => 'Username',
            self::Dosen => 'NIDN',
            self::Mahasiswa => 'NIM',
        };
    }

    /** Nama rute halaman awal setelah login. */
    public function beranda(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Dosen => 'dosen.dashboard',
            self::Mahasiswa => 'mahasiswa.dashboard',
        };
    }
}
