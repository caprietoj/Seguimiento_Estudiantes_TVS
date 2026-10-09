<?php

namespace Database\Factories;

use App\Enums\CommitmentStatus;
use App\Enums\CommitmentType;
use App\Models\Commitment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Commitment> */
class CommitmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'type' => fake()->randomElement(CommitmentType::cases())->value,
            'body' => fake()->sentence(10),
            'responsible' => fake()->name(),
            'due_on' => fake()->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
            'status' => CommitmentStatus::Pending->value,
            'due_on_estimated' => false,
            'author_id' => User::factory(),
        ];
    }

    public function late(): static
    {
        return $this->state(fn () => [
            'due_on' => fake()->dateTimeBetween('-2 months', '-1 day')->format('Y-m-d'),
            'status' => fake()->randomElement([CommitmentStatus::Pending, CommitmentStatus::InProgress])->value,
        ]);
    }
}
