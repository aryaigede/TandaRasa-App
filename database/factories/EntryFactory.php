<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Tanggal unik supaya beberapa entri satu pengguna tidak bentrok dengan unique index.
            'entry_date' => fake()->unique()->dateTimeBetween('-2 years')->format('Y-m-d'),
            'mood_score' => fake()->numberBetween(1, 5),
            'journal' => null,
        ];
    }

    public function withJournal(string $journal = 'Hari ini cukup baik.'): static
    {
        return $this->state(fn () => ['journal' => $journal]);
    }

    public function today(): static
    {
        return $this->state(fn () => ['entry_date' => now()->toDateString()]);
    }
}
