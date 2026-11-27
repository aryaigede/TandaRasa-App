<?php

use App\Actions\GetTodayEntry;
use App\Actions\SaveDailyEntry;
use App\Enums\AnalysisStatus;
use App\Models\AnalysisResult;
use App\Models\Entry;
use App\Models\User;

/*
 * Isi awal halaman check-in dan penanda "sudah check-in hari ini" (FR-06, FR-07).
 * Entri yang sudah tersimpan muncul lagi saat halaman dimuat ulang (FR-08).
 */

beforeEach(function () {
    $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 27)->setTime(10, 0));
});

test('mengembalikan null bila belum check-in hari ini', function () {
    $user = User::factory()->create();
    Entry::factory()->for($user)->create(['entry_date' => '2026-11-26']);

    expect(app(GetTodayEntry::class)->handle($user))->toBeNull();
});

test('mengembalikan entri hari ini beserta analisisnya', function () {
    $user = User::factory()->create();
    $entry = Entry::factory()->for($user)->today()->withJournal('Hari ini cukup baik.')->create();
    AnalysisResult::factory()->for($entry)->create();

    $today = app(GetTodayEntry::class)->handle($user);

    expect($today?->id)->toBe($entry->id)
        ->and($today->journal)->toBe('Hari ini cukup baik.')
        ->and($today->relationLoaded('analysis'))->toBeTrue()
        ->and($today->analysis?->status)->toBe(AnalysisStatus::Pending);
});

test('setiap akun hanya mendapat entri hari ini miliknya sendiri', function () {
    $alice = User::factory()->create();
    $budi = User::factory()->create();
    $carla = User::factory()->create();
    $aliceEntry = Entry::factory()->for($alice)->today()->create();
    $budiEntry = Entry::factory()->for($budi)->today()->create();

    expect(app(GetTodayEntry::class)->handle($alice)?->id)->toBe($aliceEntry->id)
        ->and(app(GetTodayEntry::class)->handle($budi)?->id)->toBe($budiEntry->id)
        ->and(app(GetTodayEntry::class)->handle($carla))->toBeNull();
});

test('hari ini mengikuti zona Asia/Jakarta', function () {
    $user = User::factory()->create();
    $entry = Entry::factory()->for($user)->create(['entry_date' => '2026-11-28']);

    // 27 Nov 23.30 WIB: entri 28 Nov belum "hari ini".
    $this->travelTo(now('UTC')->setDate(2026, 11, 27)->setTime(16, 30));
    expect(app(GetTodayEntry::class)->handle($user))->toBeNull();

    // 28 Nov 00.30 WIB.
    $this->travelTo(now('UTC')->setDate(2026, 11, 27)->setTime(17, 30));
    expect(app(GetTodayEntry::class)->handle($user)?->id)->toBe($entry->id);
});

test('isi check-in yang tersimpan muncul lagi saat halaman dimuat ulang', function () {
    $user = User::factory()->create();
    app(SaveDailyEntry::class)->handle($user, 2, 'Tugas numpuk, tidur cuma 4 jam.');

    $reloaded = app(GetTodayEntry::class)->handle($user);

    expect($reloaded?->mood_score)->toBe(2)
        ->and($reloaded?->journal)->toBe('Tugas numpuk, tidur cuma 4 jam.')
        ->and($reloaded?->created_at)->not->toBeNull();
});
