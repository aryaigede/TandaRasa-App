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
 * Kelas murni: tanpa database, HTTP, atau waktu sistem.
 */
final class TierCalculator
{
    /** Tanggal entri terbaru dan 6 tanggal sebelumnya. */
    private const WINDOW_DAYS = 7;

    private const RED_MIN_DATES = 3;

    /**
     * @param  array<string, RiskSignal>  $signalsByDate  kunci 'Y-m-d', hanya tanggal yang punya entri berstatus done
    * @param  string  $latestDate  'Y-m-d' entri terbaru; sinyalnya wajib ada di $signalsByDate
     *
     * @throws InvalidArgumentException bila format tanggal atau nilai sinyal tidak valid, atau sinyal tanggal terbaru belum ada (pemanggil harus menampilkan status analisis tertunda, bukan tier).
     */
    public function calculate(array $signalsByDate, string $latestDate): Tier
    {
        $latest = $this->parseDate($latestDate);

        foreach ($signalsByDate as $date => $signal) {
            $this->parseDate((string) $date);

            if (! $signal instanceof RiskSignal) {
                throw new InvalidArgumentException("Sinyal untuk tanggal {$date} harus berupa RiskSignal.");
            }
        }

        if (! array_key_exists($latestDate, $signalsByDate)) {
            throw new InvalidArgumentException(
                "Sinyal untuk tanggal entri terbaru ({$latestDate}) belum ada; tampilkan status analisis tertunda."
            );
        }

        // Semua tanggal sudah dipastikan berformat Y-m-d, jadi perbandingan string aman.
        $windowStart = $latest->modify('-'.(self::WINDOW_DAYS - 1).' days')->format('Y-m-d');

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

        return match ($signalsByDate[$latestDate]) {
            RiskSignal::Sedang, RiskSignal::Tinggi => Tier::Kuning,
            RiskSignal::Rendah => Tier::Hijau,
        };
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("Tanggal '{$value}' harus berformat Y-m-d.");
        }

        return $date;
    }
}
