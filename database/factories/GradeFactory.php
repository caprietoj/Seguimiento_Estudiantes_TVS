<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Grade> */
class GradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'school_year_id' => SchoolYear::factory(),
            'period' => 1,
            'level' => fake()->numberBetween(1, 7),
            'recorded_by' => null,
        ];
    }
}
