<?php

use App\Actions\SaveDailyEntry;
use App\Enums\AnalysisStatus;
use App\Enums\RiskSignal;
use App\Enums\Tier;
use App\Models\AnalysisResult;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/*
 * Check-in harian (FR-06, FR-07, FR-08): satu entri per pengguna per tanggal Asia/Jakarta.
 * Menyimpan lagi di hari yang sama berarti mengedit entri itu, dan setiap perubahan isi
 * menandai analisisnya sebagai tertunda (FR-09, FR-11).
 */

beforeEach(function () {
    $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 27)->setTime(10, 0));
});

function saveDailyEntry(User $user, int $moodScore, ?string $journal = null): Entry
{
    return app(SaveDailyEntry::class)->handle($user, $moodScore, $journal);
}

/**
 * @return array<string, array<int, string>>
 */
function saveDailyEntryErrors(User $user, int $moodScore, ?string $journal = null): array
{
    try {
        saveDailyEntry($user, $moodScore, $journal);
    } catch (ValidationException $e) {
        return $e->errors();
    }

    throw new RuntimeException('Validasi seharusnya gagal.');
}

test('check-in pertama hari ini membuat entri bertanggal hari ini', function () {
    $user = User::factory()->create();

    $entry = saveDailyEntry($user, 4);

    expect($entry->user_id)->toBe($user->id)
        ->and($entry->entry_date->toDateString())->toBe('2026-11-27')
        ->and($entry->mood_score)->toBe(4)
        ->and($entry->journal)->toBeNull()
        ->and($user->entries()->count())->toBe(1);
});

test('entri baru langsung punya analisis berstatus pending', function () {
    $user = User::factory()->create();

    $entry = saveDailyEntry($user, 2, 'Tugas numpuk.');

    expect($entry->relationLoaded('analysis'))->toBeTrue()
        ->and($entry->analysis->status)->toBe(AnalysisStatus::Pending)
        ->and($entry->analysis->attempts)->toBe(0)
        ->and($entry->analysis->entry_updated_at->equalTo($entry->updated_at))->toBeTrue();
});

test('jurnal disimpan tanpa spasi di ujung', function () {
    $entry = saveDailyEntry(User::factory()->create(), 3, "  Hari ini lumayan.  \n");

    expect($entry->fresh()->journal)->toBe('Hari ini lumayan.');
});

test('jurnal kosong atau hanya spasi disimpan sebagai null', function (string $journal) {
    $entry = saveDailyEntry(User::factory()->create(), 3, $journal);

    expect($entry->fresh()->journal)->toBeNull();
})->with([
    'kosong' => '',
    'spasi' => '   ',
    'baris baru dan tab' => "\n\t ",
]);

test('check-in kedua di hari yang sama mengedit entri yang sama', function () {
    $user = User::factory()->create();

    $first = saveDailyEntry($user, 2);
    $second = saveDailyEntry($user, 4, 'Sudah lebih tenang.');

    expect($second->id)->toBe($first->id)
        ->and($user->entries()->count())->toBe(1)
        ->and($second->fresh()->mood_score)->toBe(4)
        ->and($second->fresh()->journal)->toBe('Sudah lebih tenang.');
});

test('perubahan isi entri mereset analisis yang sudah selesai menjadi pending', function (int $moodScore, ?string $journal) {
    $user = User::factory()->create();
    $entry = Entry::factory()->for($user)->today()->withJournal('Tugas numpuk.')->create(['mood_score' => 2]);
    AnalysisResult::factory()->for($entry)->done(RiskSignal::Sedang, Tier::Kuning)->create([
        'safety_flag' => true,
        'emotions' => ['lelah'],
        'suggestions' => ['Istirahat sebentar.'],
        'prompt_version' => 'v1',
        'model' => 'model-contoh',
    ]);
    $this->travel(5)->minutes();

    $saved = saveDailyEntry($user, $moodScore, $journal);
    $analysis = $saved->analysis;

    expect($analysis->status)->toBe(AnalysisStatus::Pending)
        ->and($analysis->attempts)->toBe(0)
        ->and($analysis->risk_signal)->toBeNull()
        ->and($analysis->tier)->toBeNull()
        ->and($analysis->safety_flag)->toBeFalse()
        ->and($analysis->emotions)->toBeNull()
        ->and($analysis->reason)->toBeNull()
        ->and($analysis->summary)->toBeNull()
        ->and($analysis->suggestions)->toBeNull()
        ->and($analysis->source)->toBeNull()
        ->and($analysis->prompt_version)->toBeNull()
        ->and($analysis->model)->toBeNull()
        ->and($analysis->analyzed_at)->toBeNull()
        ->and($analysis->entry_updated_at->format('H:i'))->toBe('10:05')
        ->and($analysis->entry_updated_at->equalTo($saved->updated_at))->toBeTrue()
        ->and(AnalysisResult::count())->toBe(1);
})->with([
    'skor berubah' => [3, 'Tugas numpuk.'],
    'jurnal berubah' => [2, 'Tugasnya sudah beres.'],
    'jurnal dihapus' => [2, null],
]);

test('menyimpan ulang tanpa perubahan mempertahankan analisis lama', function () {
    $user = User::factory()->create();
    $entry = Entry::factory()->for($user)->today()->withJournal('Tugas numpuk.')->create(['mood_score' => 2]);
    $done = AnalysisResult::factory()->for($entry)->done(RiskSignal::Sedang, Tier::Kuning)->create();
    $this->travel(5)->minutes();

    // Spasi di ujung dibuang, jadi isinya tetap sama.
    $saved = saveDailyEntry($user, 2, '  Tugas numpuk. ');

    expect($saved->analysis->id)->toBe($done->id)
        ->and($saved->analysis->status)->toBe(AnalysisStatus::Done)
        ->and($saved->analysis->tier)->toBe(Tier::Kuning)
        ->and($saved->fresh()->updated_at->equalTo($entry->updated_at))->toBeTrue();
});

test('entri hari ini yang belum punya analisis dibuatkan analisis pending saat disimpan ulang', function () {
    $user = User::factory()->create();
    Entry::factory()->for($user)->today()->create(['mood_score' => 3]);

    $saved = saveDailyEntry($user, 3);

    expect($saved->analysis?->status)->toBe(AnalysisStatus::Pending);
});

test('check-in di hari baru membuat entri baru tanpa mengubah entri kemarin', function () {
    $user = User::factory()->create();
    $yesterday = Entry::factory()->for($user)->create([
        'entry_date' => '2026-11-26',
        'mood_score' => 1,
        'journal' => 'Kemarin berat.',
    ]);

    $today = saveDailyEntry($user, 5, 'Hari ini lebih baik.');

    expect($today->id)->not->toBe($yesterday->id)
        ->and($today->entry_date->toDateString())->toBe('2026-11-27')
        ->and($yesterday->fresh()->mood_score)->toBe(1)
        ->and($yesterday->fresh()->journal)->toBe('Kemarin berat.')
        ->and($user->entries()->count())->toBe(2);
});

test('tanggal entri mengikuti zona Asia/Jakarta', function () {
    $user = User::factory()->create();

    // 27 Nov 23.30 WIB = 27 Nov 16.30 UTC.
    $this->travelTo(now('UTC')->setDate(2026, 11, 27)->setTime(16, 30));
    $lateNight = saveDailyEntry($user, 3);

    // 28 Nov 00.30 WIB = 27 Nov 17.30 UTC: sudah hari baru di Jakarta.
    $this->travelTo(now('UTC')->setDate(2026, 11, 27)->setTime(17, 30));
    $nextDay = saveDailyEntry($user, 4);

    expect($lateNight->entry_date->toDateString())->toBe('2026-11-27')
        ->and($nextDay->entry_date->toDateString())->toBe('2026-11-28')
        ->and($nextDay->id)->not->toBe($lateNight->id)
        ->and($lateNight->fresh()->mood_score)->toBe(3);
});

test('setiap akun punya entri hariannya sendiri', function () {
    $alice = User::factory()->create();
    $budi = User::factory()->create();

    $aliceEntry = saveDailyEntry($alice, 2, 'Punya Alice.');
    $budiEntry = saveDailyEntry($budi, 5);

    expect($budiEntry->id)->not->toBe($aliceEntry->id)
        ->and($budiEntry->user_id)->toBe($budi->id)
        ->and($aliceEntry->fresh()->mood_score)->toBe(2)
        ->and($aliceEntry->fresh()->journal)->toBe('Punya Alice.')
        ->and(Entry::count())->toBe(2);
});

test('skor mood di luar 1 sampai 5 ditolak dengan pesan suportif', function (int $moodScore) {
    $user = User::factory()->create();

    expect(saveDailyEntryErrors($user, $moodScore))->toBe([
        'mood_score' => ['Pilih skor mood dari 1 sampai 5, ya.'],
    ])->and(Entry::count())->toBe(0);
})->with([0, 6, -1]);

test('jurnal lebih dari 2000 karakter ditolak tanpa menyimpan entri', function () {
    $user = User::factory()->create();

    expect(saveDailyEntryErrors($user, 3, str_repeat('a', 2001)))->toBe([
        'journal' => ['Jurnalnya maksimal 2000 karakter.'],
    ])->and(Entry::count())->toBe(0);
});

test('jurnal tepat 2000 karakter diterima', function (string $character) {
    $entry = saveDailyEntry(User::factory()->create(), 3, str_repeat($character, 2000));

    expect(mb_strlen((string) $entry->fresh()->journal))->toBe(2000);
})->with([
    'huruf biasa' => 'a',
    'huruf beraksen' => 'é',
    'emoji' => '😊',
]);

test('spasi di ujung jurnal tidak dihitung dalam batas 2000 karakter', function () {
    $entry = saveDailyEntry(User::factory()->create(), 3, '  '.str_repeat('a', 2000).'  ');

    expect(mb_strlen((string) $entry->fresh()->journal))->toBe(2000);
});

test('validasi yang gagal saat mengedit tidak mengubah entri yang sudah ada', function () {
    $user = User::factory()->create();
    $entry = saveDailyEntry($user, 2, 'Versi awal.');

    saveDailyEntryErrors($user, 9, 'Versi baru.');

    expect($entry->fresh()->mood_score)->toBe(2)
        ->and($entry->fresh()->journal)->toBe('Versi awal.');
});
