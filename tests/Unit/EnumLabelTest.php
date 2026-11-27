<?php

use App\Enums\AnalysisSource;
use App\Enums\AnalysisStatus;
use App\Enums\SupportContactType;
use App\Enums\Tier;

test('label tier untuk UI mengikuti aturan domain', function (Tier $tier, string $label) {
    expect($tier->label())->toBe($label);
})->with([
    [Tier::Hijau, 'Stabil'],
    [Tier::Kuning, 'Perlu perhatian'],
    [Tier::Merah, 'Butuh dukungan'],
]);

test('nilai enum status analisis sesuai kontrak AI', function () {
    expect(array_map(fn (AnalysisStatus $s) => $s->value, AnalysisStatus::cases()))
        ->toBe(['pending', 'processing', 'done', 'failed']);
});

test('nilai enum sumber analisis sesuai kontrak AI', function () {
    expect(array_map(fn (AnalysisSource $s) => $s->value, AnalysisSource::cases()))
        ->toBe(['ai', 'mood_rule']);
});

test('jenis kontak dukungan', function () {
    expect(array_map(fn (SupportContactType $t) => $t->value, SupportContactType::cases()))
        ->toBe(['campus_counseling', 'emergency']);
});
