<?php

namespace App\Enums;

enum RiskSignal: string
{
    case Rendah = 'rendah';
    case Sedang = 'sedang';
    case Tinggi = 'tinggi';
}
