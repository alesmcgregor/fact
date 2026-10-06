<?php

namespace Database\Factories;

use App\Models\Analysis;
use App\Models\Claim;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Claim> */
class ClaimFactory extends Factory
{
    protected $model = Claim::class;

    public function definition(): array
    {
        return [
            'analysis_id' => Analysis::factory(),
            'position' => 1,
            'statement' => fake()->sentence(),
            'classification' => 'factual',
            'verification_status' => 'insufficient_evidence',
            'evidence_score' => 0,
        ];
    }
}
