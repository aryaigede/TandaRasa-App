<?php

use App\Models\Entry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
 * Otorisasi per akun (FR-21): entri hanya bisa dilihat dan diubah pemiliknya,
 * dan hanya entri hari ini yang bisa diubah (FR-06, FR-07). Diuji dengan dua akun.
 */

beforeEach(function () {
    $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 27)->setTime(10, 0));
});

test('pemilik boleh melihat entrinya', function () {
    $owner = User::factory()->create();
    $entry = Entry::factory()->for($owner)->create();

    expect(Gate::forUser($owner)->allows('view', $entry))->toBeTrue();
});

test('akun lain tidak boleh melihat entri yang bukan miliknya', function () {
    $entry = Entry::factory()->for(User::factory())->create();
    $intruder = User::factory()->create();

    expect(Gate::forUser($intruder)->allows('view', $entry))->toBeFalse();
});

test('pemilik boleh mengubah entri hari ini', function () {
    $owner = User::factory()->create();
    $entry = Entry::factory()->for($owner)->create(['entry_date' => '2026-11-27']);

    expect(Gate::forUser($owner)->allows('update', $entry))->toBeTrue();
});

test('pemilik tidak boleh mengubah entri hari sebelumnya', function () {
    $owner = User::factory()->create();
    $entry = Entry::factory()->for($owner)->create(['entry_date' => '2026-11-26']);

    expect(Gate::forUser($owner)->allows('update', $entry))->toBeFalse();
});

test('akun lain tidak boleh mengubah entri hari ini milik orang lain', function () {
    $entry = Entry::factory()->for(User::factory())->create(['entry_date' => '2026-11-27']);
    $intruder = User::factory()->create();

    expect(Gate::forUser($intruder)->allows('update', $entry))->toBeFalse();
});

test('hari ini dihitung dengan zona Asia/Jakarta', function () {
    // 27 Nov 23.30 WIB = 27 Nov 16.30 UTC. Entri 27 Nov masih "hari ini" di Jakarta.
    $this->travelTo(now('UTC')->setDate(2026, 11, 27)->setTime(16, 30));
    $owner = User::factory()->create();
    $entry = Entry::factory()->for($owner)->create(['entry_date' => '2026-11-27']);

    expect(Gate::forUser($owner)->allows('update', $entry))->toBeTrue();

    // 28 Nov 00.30 WIB: entri 27 Nov sudah kemarin.
    $this->travelTo(now('UTC')->setDate(2026, 11, 27)->setTime(17, 30));

    expect(Gate::forUser($owner)->allows('update', $entry))->toBeFalse();
});
