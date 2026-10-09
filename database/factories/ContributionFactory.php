<?php

namespace Database\Factories;

use App\Enums\ContributionField;
use App\Models\Contribution;
use App\Models\FollowUp;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contribution> */
class ContributionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'follow_up_id' => FollowUp::factory(),
            'field' => fake()->randomElement(ContributionField::cases())->value,
            'body' => fake()->sentence(12),
            'author_id' => User::factory(),
        ];
    }
}
