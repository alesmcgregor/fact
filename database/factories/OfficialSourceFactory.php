<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\OfficialSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OfficialSource> */
class OfficialSourceFactory extends Factory
{
    protected $model = OfficialSource::class;

    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'label' => 'Official website',
            'url' => 'https://example.gov/',
            'domain' => 'example.gov',
            'language' => 'en',
            'verification_status' => 'verified',
            'priority' => 100,
            'active' => true,
        ];
    }
}
