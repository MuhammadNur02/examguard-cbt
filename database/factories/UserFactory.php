<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nim_nidn' => (string) fake()->unique()->numerify('23########'),
            'nama' => fake()->name(),
            'role' => Role::Mahasiswa,
            'password' => static::$password ??= Hash::make('password'),
            'aktif' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'nim_nidn' => 'admin'.fake()->unique()->numberBetween(1, 99999),
            'role' => Role::Admin,
        ]);
    }

    public function dosen(): static
    {
        return $this->state(fn () => [
            'nim_nidn' => (string) fake()->unique()->numerify('06########'),
            'role' => Role::Dosen,
        ]);
    }

    public function mahasiswa(): static
    {
        return $this->state(fn () => ['role' => Role::Mahasiswa]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['aktif' => false]);
    }
}
