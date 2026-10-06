<?php

namespace App\Models;

use Database\Factories\OfficialSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialSource extends Model
{
    /** @use HasFactory<OfficialSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'institution_id', 'label', 'url', 'domain', 'language', 'verification_status',
        'priority', 'active', 'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'active' => 'boolean',
            'last_verified_at' => 'immutable_datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
