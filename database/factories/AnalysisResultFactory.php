<?php

namespace Database\Factories;

use App\Enums\AnalysisSource;
use App\Enums\AnalysisStatus;
use App\Enums\RiskSignal;
use App\Enums\Tier;
use App\Models\AnalysisResult;
use App\Models\Entry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisResult>
 */
class AnalysisResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory(),
            'status' => AnalysisStatus::Pending,
            'entry_updated_at' => now(),
        ];
    }

    public function done(RiskSignal $signal = RiskSignal::Rendah, Tier $tier = Tier::Hijau): static
    {
        return $this->state(fn () => [
            'status' => AnalysisStatus::Done,
            'attempts' => 1,
            'risk_signal' => $signal,
            'tier' => $tier,
            'source' => AnalysisSource::MoodRule,
            'reason' => 'Alasan contoh untuk tes.',
            'summary' => 'Ringkasan contoh untuk tes.',
            'analyzed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => AnalysisStatus::Failed,
            'attempts' => 3,
        ]);
    }
}
