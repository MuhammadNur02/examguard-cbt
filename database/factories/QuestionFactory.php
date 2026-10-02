<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'urutan' => 0,
            'tipe' => QuestionType::Pg,
            'teks' => fake()->sentence().'?',
            'bobot' => 2,
            'kunci_esai' => null,
            'keywords' => null,
        ];
    }

    /**
     * Soal PG dengan opsi A.. sebanyak $jumlahOpsi dan tepat satu kunci.
     */
    public function pg(int $jumlahOpsi = 4, string $kunci = 'A'): static
    {
        return $this->state(fn () => ['tipe' => QuestionType::Pg, 'kunci_esai' => null, 'keywords' => null])
            ->afterCreating(function (Question $question) use ($jumlahOpsi, $kunci) {
                foreach (array_slice(['A', 'B', 'C', 'D', 'E'], 0, $jumlahOpsi) as $label) {
                    $question->options()->create([
                        'label' => $label,
                        'teks' => 'Opsi '.$label.' '.fake()->word(),
                        'is_correct' => $label === $kunci,
                        'posisi_tetap' => false,
                    ]);
                }
            });
    }

    public function esai(): static
    {
        return $this->state(fn () => [
            'tipe' => QuestionType::Esai,
            'bobot' => 10,
            'kunci_esai' => 'Middleware menyaring permintaan HTTP sebelum diteruskan ke controller.',
            'keywords' => ['middleware', 'permintaan'],
        ]);
    }
}
