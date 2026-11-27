<?php

namespace App\Models;

use App\Enums\SupportContactType;
use Carbon\CarbonImmutable;
use Database\Factories\SupportContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Kontak Pusat Dukungan (FR-18, FR-19, FR-24). Isinya hanya dari sumber resmi.
 *
 * @property int $id
 * @property SupportContactType $type
 * @property string $name
 * @property string|null $phone
 * @property string|null $hours
 * @property string|null $url
 * @property string $source
 * @property CarbonImmutable|null $verified_at
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'name', 'phone', 'hours', 'url', 'source', 'verified_at', 'sort_order'])]
class SupportContact extends Model
{
    /** @use HasFactory<SupportContactFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SupportContactType::class,
            'verified_at' => 'immutable_date',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Konseling kampus lebih dulu, lalu menurut urutan tampil.
     *
     * @param  Builder<SupportContact>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw('case when type = ? then 0 else 1 end', [SupportContactType::CampusCounseling->value])
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
