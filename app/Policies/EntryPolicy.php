<?php

namespace App\Policies;

use App\Models\Entry;
use App\Models\User;

/**
 * Otorisasi per akun (FR-21). Entri hanya bisa diubah pada harinya sendiri (FR-06, FR-07).
 */
class EntryPolicy
{
    public function view(User $user, Entry $entry): bool
    {
        return $entry->user_id === $user->id;
    }

    public function update(User $user, Entry $entry): bool
    {
        return $this->view($user, $entry) && $entry->isToday();
    }
}
