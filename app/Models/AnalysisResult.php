<?php

namespace App\Models;

use App\Enums\AnalysisSource;
use App\Enums\AnalysisStatus;
use App\Enums\RiskSignal;
use App\Enums\Tier;
use Carbon\CarbonImmutable;
use Database\Factories\AnalysisResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hasil analisis satu entri (docs/ai-contract.md bagian 2).
 *
 * @property int $id
 * @property int $entry_id
 * @property AnalysisStatus $status
 * @property int $attempts
 * @property RiskSignal|null $risk_signal
 * @property Tier|null $tier
 * @property bool $safety_flag
 * @property list<string>|null $emotions
 * @property string|null $reason
 * @property string|null $summary
 * @property list<string>|null $suggestions
 * @property AnalysisSource|null $source
 * @property string|null $prompt_version
 * @property string|null $model
 * @property CarbonImmutable $entry_updated_at
 * @property CarbonImmutable|null $analyzed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Entry $entry
 */
#[Fillable([
    'status', 'attempts', 'risk_signal', 'tier', 'safety_flag', 'emotions', 'reason', 'summary',
    'suggestions', 'source', 'prompt_version', 'model', 'entry_updated_at', 'analyzed_at',
])]
class AnalysisResult extends Model
{
    /** @use HasFactory<AnalysisResultFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
        'safety_flag' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'attempts' => 'integer',
            'risk_signal' => RiskSignal::class,
            'tier' => Tier::class,
            'safety_flag' => 'boolean',
            'emotions' => 'array',
            'suggestions' => 'array',
            'source' => AnalysisSource::class,
            'entry_updated_at' => 'immutable_datetime',
            'analyzed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
