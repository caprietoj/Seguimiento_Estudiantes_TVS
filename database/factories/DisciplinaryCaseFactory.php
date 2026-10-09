<?php

namespace Database\Factories;

use App\Enums\DisciplinaryCaseStatus;
use App\Enums\DisciplinaryCaseType;
use App\Models\DisciplinaryCase;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DisciplinaryCase> */
class DisciplinaryCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'occurred_on' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'type' => fake()->randomElement(DisciplinaryCaseType::cases())->value,
            'description' => fake()->sentence(12),
            'action_taken' => fake()->optional()->sentence(10),
            'status' => DisciplinaryCaseStatus::Open->value,
            'author_id' => User::factory(),
        ];
    }
}
