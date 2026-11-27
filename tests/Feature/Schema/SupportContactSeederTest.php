<?php

use App\Enums\SupportContactType;
use App\Models\SupportContact;
use Database\Seeders\SupportContactSeeder;

/*
 * Pusat Dukungan (FR-18, FR-19, FR-24): kontak tidak boleh dikarang.
 * Seeder awal hanya berisi placeholder yang jelas ditandai dan belum terverifikasi.
 */

test('seeder membuat placeholder konseling kampus dan layanan darurat', function () {
    $this->seed(SupportContactSeeder::class);

    expect(SupportContact::where('type', SupportContactType::CampusCounseling)->count())->toBe(1)
        ->and(SupportContact::where('type', SupportContactType::Emergency)->count())->toBe(1);
});

test('placeholder tidak berisi nomor, jam, atau tautan karangan', function () {
    $this->seed(SupportContactSeeder::class);

    SupportContact::all()->each(function (SupportContact $contact) {
        expect($contact->name)->toStartWith('[Placeholder]')
            ->and($contact->phone)->toBeNull()
            ->and($contact->hours)->toBeNull()
            ->and($contact->url)->toBeNull()
            ->and($contact->verified_at)->toBeNull();
    });
});

test('seeder aman dijalankan berulang kali', function () {
    $this->seed(SupportContactSeeder::class);
    $this->seed(SupportContactSeeder::class);

    expect(SupportContact::count())->toBe(2);
});

test('kontak terurut konseling kampus dulu lalu urutan tampil', function () {
    SupportContact::factory()->create(['type' => SupportContactType::Emergency, 'sort_order' => 0, 'name' => 'Darurat']);
    SupportContact::factory()->create(['type' => SupportContactType::CampusCounseling, 'sort_order' => 2, 'name' => 'Konseling B']);
    SupportContact::factory()->create(['type' => SupportContactType::CampusCounseling, 'sort_order' => 1, 'name' => 'Konseling A']);

    expect(SupportContact::query()->ordered()->pluck('name')->all())
        ->toBe(['Konseling A', 'Konseling B', 'Darurat']);
});
