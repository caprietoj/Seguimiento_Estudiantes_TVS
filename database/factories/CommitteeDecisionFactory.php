<?php

namespace Database\Factories;

use App\Enums\CommitteeDecision;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\CommitteeDecision> */
class CommitteeDecisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'decided_on' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'decision' => fake()->randomElement(CommitteeDecision::cases())->value,
            'notes' => fake()->optional()->sentence(10),
            'author_id' => User::factory(),
        ];
    }
}
