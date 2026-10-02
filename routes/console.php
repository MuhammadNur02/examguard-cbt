<?php

use Illuminate\Support\Facades\Schedule;

// Kirim otomatis attempt yang waktunya habis walau peramban mahasiswa tertutup.
// Jalankan penjadwal dengan `php artisan schedule:work` (pengembangan) atau cron.
Schedule::command('ujian:tutup-kedaluwarsa')->everyMinute()->withoutOverlapping();
