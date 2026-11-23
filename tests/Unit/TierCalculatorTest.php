<?php

use App\Actions\TierCalculator;
use App\Enums\RiskSignal;
use App\Enums\Tier;

/*
 * Skenario 1-7 dari docs/ai-contract.md bagian 5 (FR-10, NFR-12).
 * H (TIER_LATEST_DATE) = tanggal entri terbaru.
 */

const TIER_LATEST_DATE = '2026-11-20';

/**
 * Membuat sinyal dari jarak hari ke H, misalnya [4 => Tinggi] untuk H-4.
 *
 * @param  array<int, RiskSignal>  $signalsByDaysAgo
 * @return array<string, RiskSignal>
 */
function tierSignals(array $signalsByDaysAgo): array
{
    $signals = [];

    foreach ($signalsByDaysAgo as $daysAgo => $signal) {
        $date = (new DateTimeImmutable(TIER_LATEST_DATE))->modify(sprintf('%+d days', -$daysAgo))->format('Y-m-d');
        $signals[$date] = $signal;
    }

    return $signals;
}

/**
 * Tujuh tanggal penuh (H-6 sampai H) bernilai rendah, kecuali yang ditimpa.
 *
 * @param  array<int, RiskSignal>  $overrides
 * @return array<string, RiskSignal>
 */
function tierWeek(array $overrides = []): array
{
    return tierSignals($overrides + array_fill(0, 7, RiskSignal::Rendah));
}

function tierFor(array $signalsByDate, string $latestDate = TIER_LATEST_DATE): Tier
{
    return (new TierCalculator)->calculate($signalsByDate, $latestDate);
}

test('skenario 1: semua rendah menghasilkan hijau', function () {
    expect(tierFor(tierWeek()))->toBe(Tier::Hijau);
});

test('skenario 2: terbaru sedang dan lainnya rendah menghasilkan kuning', function () {
    expect(tierFor(tierWeek([0 => RiskSignal::Sedang])))->toBe(Tier::Kuning);
});

test('skenario 3: dua tanggal tinggi menghasilkan kuning, bukan merah', function () {
    expect(tierFor(tierWeek([4 => RiskSignal::Tinggi, 0 => RiskSignal::Tinggi])))->toBe(Tier::Kuning);
});

test('skenario 4: tiga tanggal tinggi dalam jendela menghasilkan merah', function () {
    expect(tierFor(tierSignals([
        5 => RiskSignal::Tinggi,
        2 => RiskSignal::Tinggi,
        0 => RiskSignal::Tinggi,
    ])))->toBe(Tier::Merah);
});

test('skenario 5: tinggi di H-7 berada di luar jendela sehingga kuning', function () {
    expect(tierFor(tierSignals([
        7 => RiskSignal::Tinggi,
        3 => RiskSignal::Tinggi,
        0 => RiskSignal::Tinggi,
    ])))->toBe(Tier::Kuning);
});

test('skenario 6: entri terbaru diedit dari tinggi ke rendah mengubah merah menjadi hijau', function () {
    $signals = tierSignals([
        2 => RiskSignal::Tinggi,
        1 => RiskSignal::Tinggi,
        0 => RiskSignal::Tinggi,
    ]);

    expect(tierFor($signals))->toBe(Tier::Merah);

    $signals[TIER_LATEST_DATE] = RiskSignal::Rendah;

    expect(tierFor($signals))->toBe(Tier::Hijau);
});

test('skenario 7: tiga tanggal tanpa jurnal dengan skor 1 menghasilkan merah', function () {
    // Skor 1 tanpa jurnal sudah dipetakan menjadi sinyal tinggi oleh AnalyzeEntry.
    expect(tierFor(tierSignals([
        4 => RiskSignal::Tinggi,
        1 => RiskSignal::Tinggi,
        0 => RiskSignal::Tinggi,
    ])))->toBe(Tier::Merah);
});

test('satu entri buruk tidak memicu merah', function () {
    expect(tierFor(tierSignals([0 => RiskSignal::Tinggi])))->toBe(Tier::Kuning);
});

test('tiga tanggal tinggi yang tersebar lebih dari tujuh hari tidak memicu merah', function () {
    expect(tierFor(tierSignals([
        14 => RiskSignal::Tinggi,
        7 => RiskSignal::Tinggi,
        0 => RiskSignal::Tinggi,
    ])))->toBe(Tier::Kuning);
});

test('H-6 masih termasuk jendela', function () {
    expect(tierFor(tierSignals([
        6 => RiskSignal::Tinggi,
        3 => RiskSignal::Tinggi,
        0 => RiskSignal::Tinggi,
    ])))->toBe(Tier::Merah);
});

test('tanggal setelah entri terbaru diabaikan', function () {
    expect(tierFor(tierSignals([
        2 => RiskSignal::Tinggi,
        0 => RiskSignal::Tinggi,
        -1 => RiskSignal::Tinggi,
    ]);

    expect($signals)->toHaveKey('2026-11-21')
        ->and(tierFor($signals))->toBe(Tier::Kuning);
});

test('merah dinilai dari pola walaupun entri terbaru rendah', function () {
    expect(tierFor(tierWeek([
        3 => RiskSignal::Tinggi,
        2 => RiskSignal::Tinggi,
        1 => RiskSignal::Tinggi,
    ])))->toBe(Tier::Merah);
});

test('sinyal sedang tidak dihitung menuju merah', function () {
    expect(tierFor(tierWeek([
        2 => RiskSignal::Sedang,
        1 => RiskSignal::Sedang,
        0 => RiskSignal::Tinggi,
    ])))->toBe(Tier::Kuning);
});

test('terbaru rendah dengan riwayat sedang tetap hijau', function () {
    expect(tierFor(tierWeek([2 => RiskSignal::Sedang, 1 => RiskSignal::Sedang])))->toBe(Tier::Hijau);
});

test('jendela melewati pergantian bulan dan tahun', function () {
    $signals = [
        '2026-12-27' => RiskSignal::Tinggi,
        '2026-12-31' => RiskSignal::Tinggi,
        '2027-01-02' => RiskSignal::Tinggi,
    ];

    expect(tierFor($signals, '2027-01-02'))->toBe(Tier::Merah)
        ->and(tierFor(['2026-12-26' => RiskSignal::Tinggi] + array_slice($signals, 1), '2027-01-02'))->toBe(Tier::Kuning);
});

test('urutan masukan tidak mengubah hasil', function () {
    $signals = tierSignals([5 => RiskSignal::Tinggi, 2 => RiskSignal::Tinggi, 0 => RiskSignal::Tinggi]);

    expect(tierFor(array_reverse($signals)))->toBe(tierFor($signals));
});

test('tanggal entri terbaru yang tidak valid ditolak', function (string $date) {
    tierFor([], $date);
})->with(['20-11-2026', '2026-02-30', ''])->throws(InvalidArgumentException::class);

test('sinyal tanggal terbaru yang belum ada ditolak, bukan dianggap hijau', function () {
    // Analisis entri H masih pending/failed; pemanggil harus menampilkan "analisis tertunda".
    tierFor(tierSignals([2 => RiskSignal::Tinggi, 1 => RiskSignal::Tinggi]));
})->throws(InvalidArgumentException::class, 'belum ada');

test('kunci tanggal yang tidak berformat Y-m-d ditolak', function (string $badKey) {
    tierFor([
        $badKey => RiskSignal::Tinggi,
        TIER_LATEST_DATE => RiskSignal::Tinggi,
    ]);
})->with([
    'cast string Carbon' => '2026-11-19 00:00:00',
    'format ISO' => '2026-11-19T08:00',
    'tanpa nol di depan' => '2026-11-9',
    'tanggal mustahil' => '2026-02-30',
])->throws(InvalidArgumentException::class, 'berformat Y-m-d');

test('nilai sinyal yang bukan RiskSignal ditolak', function () {
    tierFor([TIER_LATEST_DATE => 'tinggi']);
})->throws(InvalidArgumentException::class, 'RiskSignal');
