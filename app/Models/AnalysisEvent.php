<?php

namespace App\Models;

use Database\Factories\AnalysisEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisEvent extends Model
{
    /** @use HasFactory<AnalysisEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['analysis_id', 'stage', 'state', 'details', 'created_at'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }
}
