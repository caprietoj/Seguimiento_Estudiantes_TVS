<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Enrollment> */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'group_id' => Group::factory(),
            'school_year_id' => SchoolYear::factory(),
        ];
    }
}
