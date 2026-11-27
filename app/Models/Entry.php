<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Entri harian: satu skor mood dan paling banyak satu jurnal per pengguna per tanggal (FSD 2.9.3).
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $entry_date
 * @property int $mood_score
 * @property string|null $journal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read AnalysisResult|null $analysis
 */
#[Fillable(['entry_date', 'mood_score', 'journal'])]
#[Hidden(['journal'])]
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'immutable_date',
            'mood_score' => 'integer',
            // NFR-06: isi jurnal tidak tersimpan sebagai teks biasa di database.
            'journal' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<AnalysisResult, $this>
     */
    public function analysis(): HasOne
    {
        return $this->hasOne(AnalysisResult::class);
    }

    /**
     * Apakah entri ini milik hari ini menurut zona aplikasi (Asia/Jakarta).
     */
    public function isToday(): bool
    {
        return $this->entry_date->toDateString() === now()->toDateString();
    }
}
