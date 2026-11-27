<?php

namespace App\Actions;

use App\Models\Entry;
use App\Models\User;

/**
 * Entri hari ini (Asia/Jakarta) milik pengguna, beserta analisisnya, atau null bila belum
 * check-in. Dipakai untuk isi awal halaman check-in dan penanda di dashboard (FR-06, FR-08).
 */
class GetTodayEntry
{
    public function handle(User $user): ?Entry
    {
        return $user->entries()
            ->onDate(now()->toDateString())
            ->with('analysis')
            ->first();
    }
}
