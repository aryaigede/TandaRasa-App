<?php

namespace App\Actions;

use App\Enums\RiskSignal;
use App\Enums\Tier;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Menentukan tier dari sinyal risiko per tanggal (FR-10).
 *
 * Kelas murni: tanpa database, HTTP, atau waktu sistem. Aturannya ada di
 * docs/ai-contract.md bagian 4. LLM tidak pernah menentukan tier.
 */
final class TierCalculator
{
    /** Tanggal entri terbaru dan 6 tanggal sebelumnya. */
    private const WINDOW_DAYS = 7;

    private const RED_MIN_DATES = 3;

    /**
     * @param  array<string, RiskSignal>  $signalsByDate  kunci 'Y-m-d', hanya tanggal yang punya entri berstatus done
     * @param  string  $latestDate  'Y-m-d' entri terbaru
     */
    public function calculate(array $signalsByDate, string $latestDate): Tier
    {
        $windowStart = $this->windowStart($latestDate);

        // Satu kunci per tanggal, jadi yang dihitung adalah tanggal berbeda.
        $highRiskDates = array_filter(
            $signalsByDate,
            fn (RiskSignal $signal, string $date): bool => $signal === RiskSignal::Tinggi
                && $date >= $windowStart
                && $date <= $latestDate,
            ARRAY_FILTER_USE_BOTH,
        );

        if (count($highRiskDates) >= self::RED_MIN_DATES) {
            return Tier::Merah;
        }

        return match ($signalsByDate[$latestDate] ?? null) {
            RiskSignal::Sedang, RiskSignal::Tinggi => Tier::Kuning,
            default => Tier::Hijau,
        };
    }

    private function windowStart(string $latestDate): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $latestDate, new DateTimeZone('UTC'));

        if ($date === false || $date->format('Y-m-d') !== $latestDate) {
            throw new InvalidArgumentException('Tanggal entri terbaru harus berformat Y-m-d.');
        }

        return $date->modify('-'.(self::WINDOW_DAYS - 1).' days')->format('Y-m-d');
    }
}
