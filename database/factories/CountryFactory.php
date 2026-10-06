<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Country> */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return ['code' => 'TR', 'name_key' => 'country.turkiye', 'flag' => '🇹🇷', 'active' => true];
    }
}
