<?php

namespace App\Models;

use Database\Factories\EvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    /** @use HasFactory<EvidenceFactory> */
    use HasFactory;

    protected $fillable = [
        'analysis_id', 'claim_id', 'title', 'publisher', 'url', 'source_type', 'relationship',
        'summary', 'published_at', 'retrieved_at', 'identity_url',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'retrieved_at' => 'immutable_datetime',
        ];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}
