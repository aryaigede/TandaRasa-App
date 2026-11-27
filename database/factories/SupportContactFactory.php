<?php

namespace Database\Factories;

use App\Enums\SupportContactType;
use App\Models\SupportContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hanya untuk tes. Data asli Pusat Dukungan berasal dari sumber resmi kampus.
 *
 * @extends Factory<SupportContact>
 */
class SupportContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => SupportContactType::CampusCounseling,
            'name' => 'Layanan contoh '.fake()->unique()->numberBetween(1, 9999),
            'phone' => null,
            'hours' => null,
            'url' => null,
            'source' => 'Data contoh untuk tes',
            'verified_at' => null,
            'sort_order' => 0,
        ];
    }
}
