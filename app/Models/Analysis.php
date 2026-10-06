<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Database\Factories\AnalysisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Analysis extends Model
{
    /** @use HasFactory<AnalysisFactory> */
    use HasFactory;

    protected $fillable = [
        'public_id', 'submitted_url', 'normalized_url', 'locale', 'status', 'stage', 'failure_code',
        'content_title', 'content_excerpt', 'content_language', 'summary', 'verification_status',
        'evidence_score', 'manipulation_level', 'manipulation_signals', 'limitations', 'demo_mode', 'completed_at', 'expires_at',
        'request_fingerprint', 'include_perspectives', 'content_metadata', 'research_context', 'supplied_transcript',
    ];

    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'content_metadata' => 'array',
            'research_context' => 'array',
            'include_perspectives' => 'boolean',
            'limitations' => 'array',
            'manipulation_signals' => 'array',
            'demo_mode' => 'boolean',
            'evidence_score' => 'integer',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Analysis $analysis): void {
            $analysis->public_id ??= Str::random(32);
            $analysis->expires_at ??= now()->addDays((int) config('fact.retention_days', 90));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class)->orderBy('position');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AnalysisEvent::class)->orderBy('id');
    }
}
