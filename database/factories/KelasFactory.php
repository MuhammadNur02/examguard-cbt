<?php

namespace Database\Factories;

use App\Models\Kelas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kelas>
 */
class KelasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'PTI '.fake()->unique()->numberBetween(2020, 2030).' '.fake()->randomElement(['A', 'B', 'C']),
            'keterangan' => null,
        ];
    }
}
