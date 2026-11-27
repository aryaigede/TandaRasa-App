<?php

namespace Database\Seeders;

use App\Enums\SupportContactType;
use App\Models\SupportContact;
use Illuminate\Database\Seeder;

/**
 * Placeholder Pusat Dukungan. Nomor, jam layanan, dan tautan sengaja dikosongkan sampai tim
 * mendapat data dari sumber resmi kampus. Jangan mengisi dengan data karangan.
 */
class SupportContactSeeder extends Seeder
{
    public function run(): void
    {
        $placeholders = [
            ['type' => SupportContactType::CampusCounseling, 'name' => '[Placeholder] Layanan konseling kampus'],
            ['type' => SupportContactType::Emergency, 'name' => '[Placeholder] Layanan darurat'],
        ];

        foreach ($placeholders as $contact) {
            SupportContact::updateOrCreate(
                ['type' => $contact['type'], 'name' => $contact['name']],
                [
                    'phone' => null,
                    'hours' => null,
                    'url' => null,
                    'source' => 'Belum ada: menunggu data dari sumber resmi kampus',
                    'verified_at' => null,
                    'sort_order' => 0,
                ],
            );
        }
    }
}
