<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ujian tidak dapat dimulai atau dilanjutkan (di luar jadwal, sudah selesai, dll.).
 * Pesan ditujukan langsung kepada mahasiswa.
 */
class UjianTidakTersedia extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 403)
    {
        parent::__construct($message);
    }
}
