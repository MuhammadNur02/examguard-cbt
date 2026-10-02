<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ujian tidak dapat dimulai atau dilanjutkan (di luar jadwal, sudah selesai, dll.).
 * Pesan ditujukan langsung kepada mahasiswa. $data ikut dikirim pada respons JSON
 * (mis. status attempt terbaru agar layar ujian dapat membeku).
 */
class UjianTidakTersedia extends RuntimeException
{
    /** @param  array<string, mixed>  $data */
    public function __construct(string $message, public readonly int $status = 403, public readonly array $data = [])
    {
        parent::__construct($message);
    }
}
