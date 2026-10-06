<?php

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name_key', 'flag', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class);
    }
}
