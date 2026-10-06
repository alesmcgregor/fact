<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Institution> */
class InstitutionFactory extends Factory
{
    protected $model = Institution::class;

    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => fake()->company(),
            'type' => 'government',
            'priority' => 100,
            'active' => true,
        ];
    }
}
