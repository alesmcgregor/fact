<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Analysis> */
class AnalysisFactory extends Factory
{
    protected $model = Analysis::class;

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => AnalysisStatus::Completed,
            'stage' => 'complete',
            'summary' => 'Analysis completed.',
            'verification_status' => 'insufficient_evidence',
            'completed_at' => now(),
        ]);
    }

    public function definition(): array
    {
        return [
            'public_id' => Str::random(32),
            'submitted_url' => 'https://example.org/article',
            'normalized_url' => 'https://example.org/article',
            'locale' => 'en',
            'status' => AnalysisStatus::Queued,
            'stage' => 'queued',
            'expires_at' => now()->addDays(90),
        ];
    }
}
