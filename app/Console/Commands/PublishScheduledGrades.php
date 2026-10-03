<?php

namespace App\Console\Commands;

use App\Services\PublicationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Publikasi nilai terjadwal (FR-08.1). Dijadwalkan tiap menit (routes/console.php). */
#[Signature('nilai:terbitkan-terjadwal')]
#[Description('Publikasikan nilai final ujian yang jadwal publikasinya sudah tiba.')]
class PublishScheduledGrades extends Command
{
    public function handle(PublicationService $publikasi): int
    {
        $jumlah = $publikasi->jalankanTerjadwal();
        $this->info("{$jumlah} jadwal publikasi nilai dijalankan.");

        return self::SUCCESS;
    }
}
