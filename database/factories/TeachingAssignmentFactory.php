<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeachingAssignment> */
class TeachingAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'group_id' => Group::factory(),
            'subject_id' => Subject::factory(),
            'school_year_id' => SchoolYear::factory(),
        ];
    }
}
