<?php

use App\Enums\AnalysisSource;
use App\Enums\AnalysisStatus;
use App\Enums\RiskSignal;
use App\Enums\Tier;
use App\Models\AnalysisResult;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/*
 * Skema data sesuai docs/api-mapping.md bagian 1 (PB01, FR-05, FR-06, FR-07, FR-21, NFR-06).
 */

test('entri milik pengguna dan bisa diambil lewat relasi', function () {
    $user = User::factory()->create();
    $entry = Entry::factory()->for($user)->create(['mood_score' => 4]);

    expect($entry->user->is($user))->toBeTrue()
        ->and($user->entries)->toHaveCount(1)
        ->and($user->entries->first()->mood_score)->toBe(4);
});

test('satu pengguna hanya boleh punya satu entri per tanggal', function () {
    $user = User::factory()->create();
    Entry::factory()->for($user)->create(['entry_date' => '2026-11-27']);

    Entry::factory()->for($user)->create(['entry_date' => '2026-11-27']);
})->throws(UniqueConstraintViolationException::class);

test('pengguna berbeda boleh punya entri di tanggal yang sama', function () {
    Entry::factory()->for(User::factory())->create(['entry_date' => '2026-11-27']);
    Entry::factory()->for(User::factory())->create(['entry_date' => '2026-11-27']);

    expect(Entry::count())->toBe(2);
});

test('tanggal entri disimpan sebagai tanggal kalender', function () {
    $entry = Entry::factory()->create(['entry_date' => '2026-11-27']);

    expect($entry->fresh()->entry_date->format('Y-m-d'))->toBe('2026-11-27');
});

test('entri boleh tanpa jurnal', function () {
    $entry = Entry::factory()->create(['journal' => null]);

    expect($entry->fresh()->journal)->toBeNull();
});

test('isi jurnal terenkripsi di database tetapi terbaca lewat model', function () {
    $entry = Entry::factory()->create(['journal' => 'Hari ini cukup melelahkan.']);

    $raw = DB::table('entries')->where('id', $entry->id)->value('journal');

    expect($raw)->not->toBe('Hari ini cukup melelahkan.')
        ->and($raw)->not->toContain('melelahkan')
        ->and($entry->fresh()->journal)->toBe('Hari ini cukup melelahkan.');
});

test('isi jurnal tidak ikut saat entri diubah menjadi array atau JSON', function () {
    $entry = Entry::factory()->create(['journal' => 'Rahasia hari ini.']);

    expect($entry->toArray())->not->toHaveKey('journal')
        ->and($entry->toJson())->not->toContain('Rahasia');
});

test('hasil analisis baru berstatus pending dengan nilai awal yang aman', function () {
    $result = AnalysisResult::factory()->create();
    $result->refresh();

    expect($result->status)->toBe(AnalysisStatus::Pending)
        ->and($result->attempts)->toBe(0)
        ->and($result->safety_flag)->toBeFalse()
        ->and($result->risk_signal)->toBeNull()
        ->and($result->tier)->toBeNull()
        ->and($result->source)->toBeNull();
});

test('hasil analisis menyimpan enum, daftar, dan waktu dengan tipe yang benar', function () {
    $result = AnalysisResult::factory()->create([
        'status' => AnalysisStatus::Done,
        'risk_signal' => RiskSignal::Sedang,
        'tier' => Tier::Kuning,
        'source' => AnalysisSource::Ai,
        'safety_flag' => false,
        'emotions' => ['lelah', 'cemas'],
        'suggestions' => ['Tarik napas pelan lima kali.'],
        'reason' => 'Kamu menulis soal tugas yang menumpuk.',
        'summary' => 'Kamu sedang dalam tekanan.',
        'prompt_version' => 'v1',
        'model' => 'model-palsu',
        'analyzed_at' => '2026-11-27 10:00:00',
    ])->fresh();

    expect($result->status)->toBe(AnalysisStatus::Done)
        ->and($result->risk_signal)->toBe(RiskSignal::Sedang)
        ->and($result->tier)->toBe(Tier::Kuning)
        ->and($result->source)->toBe(AnalysisSource::Ai)
        ->and($result->emotions)->toBe(['lelah', 'cemas'])
        ->and($result->suggestions)->toBe(['Tarik napas pelan lima kali.'])
        ->and($result->analyzed_at?->format('Y-m-d H:i'))->toBe('2026-11-27 10:00')
        ->and($result->entry_updated_at)->not->toBeNull();
});

test('satu entri hanya punya satu hasil analisis', function () {
    $entry = Entry::factory()->create();
    AnalysisResult::factory()->for($entry)->create();

    AnalysisResult::factory()->for($entry)->create();
})->throws(UniqueConstraintViolationException::class);

test('entri dan hasil analisis saling terhubung', function () {
    $result = AnalysisResult::factory()->create();

    expect($result->entry->analysis->is($result))->toBeTrue();
});

test('hapus entri ikut menghapus hasil analisisnya', function () {
    $result = AnalysisResult::factory()->create();

    $result->entry->delete();

    expect(AnalysisResult::count())->toBe(0);
});

test('hapus akun ikut menghapus semua entri dan hasil analisisnya', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    AnalysisResult::factory()->for(Entry::factory()->for($user))->create();
    AnalysisResult::factory()->for(Entry::factory()->for($user)->state(['entry_date' => '2026-11-26']))->create();
    AnalysisResult::factory()->for(Entry::factory()->for($other))->create();

    $user->delete();

    expect(Entry::count())->toBe(1)
        ->and(Entry::first()->user_id)->toBe($other->id)
        ->and(AnalysisResult::count())->toBe(1);
});

test('kolom persetujuan dan pengingat tersedia di pengguna', function () {
    $user = User::factory()->create();
    $user->refresh();

    expect($user->consent_version)->toBeNull()
        ->and($user->consented_at)->toBeNull()
        ->and($user->reminder_enabled)->toBeFalse()
        ->and($user->reminder_time)->toBeNull();

    $user->forceFill([
        'consent_version' => 'v1',
        'consented_at' => '2026-11-27 09:00:00',
        'reminder_enabled' => true,
        'reminder_time' => '20:00',
    ])->save();
    $user->refresh();

    expect($user->consent_version)->toBe('v1')
        ->and($user->consented_at?->format('Y-m-d H:i'))->toBe('2026-11-27 09:00')
        ->and($user->reminder_enabled)->toBeTrue()
        ->and($user->reminder_time)->toStartWith('20:00');
});
