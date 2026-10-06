<?php

namespace Database\Factories;

use App\Models\Analysis;
use App\Models\AnalysisEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnalysisEvent> */
class AnalysisEventFactory extends Factory
{
    protected $model = AnalysisEvent::class;

    public function definition(): array
    {
        return [
            'analysis_id' => Analysis::factory(),
            'stage' => 'queued',
            'state' => 'complete',
            'details' => [],
            'created_at' => now(),
        ];
    }
}
