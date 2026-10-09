<?php

namespace Database\Factories;

use App\Models\FollowUp;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FollowUp> */
class FollowUpFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_year_id' => SchoolYear::factory(),
            'period' => fake()->numberBetween(1, 3),
            'number' => fake()->numberBetween(1, 2),
        ];
    }
}
