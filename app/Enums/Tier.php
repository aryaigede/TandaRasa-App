<?php

namespace App\Enums;

enum Tier: string
{
    case Hijau = 'hijau';
    case Kuning = 'kuning';
    case Merah = 'merah';

    /**
     * Label yang dilihat pengguna. Nama internal tetap hijau/kuning/merah.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hijau => 'Stabil',
            self::Kuning => 'Perlu perhatian',
            self::Merah => 'Butuh dukungan',
        };
    }
}
