<?php

namespace Database\Factories;

use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Section> */
class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Secundaria (6.° a 9.°)', 'Media (10.° y 11.°)']),
        ];
    }
}
