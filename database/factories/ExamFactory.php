<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dosen_id' => User::factory()->dosen(),
            'judul' => 'Ujian '.fake()->words(2, true),
            'mata_kuliah' => fake()->randomElement(['Pemrograman Web', 'Basis Data', 'Jaringan Komputer']),
            'mulai' => now()->subMinutes(5),
            'durasi_menit' => 90,
            'batas_pelanggaran' => 3,
            'acak_soal' => true,
            'acak_opsi' => true,
            'pool_size' => null,
            'status' => ExamStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ExamStatus::Draft]);
    }

    /** Jadwal sudah lewat. */
    public function selesai(): static
    {
        return $this->state(fn () => ['mulai' => now()->subHours(3), 'durasi_menit' => 60]);
    }

    /** Jadwal belum dimulai. */
    public function akanDatang(): static
    {
        return $this->state(fn () => ['mulai' => now()->addDay()]);
    }
}
