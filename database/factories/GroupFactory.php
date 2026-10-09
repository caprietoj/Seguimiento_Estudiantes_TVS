<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        $grade = fake()->numberBetween(6, 11);

        return [
            'code' => $grade.strtoupper(fake()->unique()->randomLetter()),
            'grade' => $grade,
            'section_id' => Section::factory(),
            'school_year_id' => SchoolYear::factory(),
            'director_id' => null,
        ];
    }
}
