<?php

namespace Database\Factories;

use App\Models\Analysis;
use App\Models\Claim;
use App\Models\Evidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Evidence> */
class EvidenceFactory extends Factory
{
    protected $model = Evidence::class;

    public function definition(): array
    {
        return [
            'analysis_id' => Analysis::factory(),
            'claim_id' => Claim::factory(),
            'title' => fake()->sentence(),
            'publisher' => fake()->domainName(),
            'url' => 'https://example.org/evidence',
            'source_type' => 'independent',
            'relationship' => 'supports',
            'summary' => fake()->sentence(),
            'retrieved_at' => now(),
        ];
    }
}
