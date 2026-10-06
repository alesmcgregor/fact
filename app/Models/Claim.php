<?php

namespace App\Models;

use Database\Factories\ClaimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Claim extends Model
{
    /** @use HasFactory<ClaimFactory> */
    use HasFactory;

    protected $fillable = [
        'analysis_id', 'position', 'statement', 'classification', 'verification_status', 'explanation',
        'evidence_score', 'supporting_official_count', 'supporting_independent_count', 'contradicting_count',
    ];

    protected function casts(): array
    {
        return ['evidence_score' => 'integer'];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
