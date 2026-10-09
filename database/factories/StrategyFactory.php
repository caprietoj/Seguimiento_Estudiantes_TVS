<?php

namespace Database\Factories;

use App\Enums\StrategyType;
use App\Models\FollowUp;
use App\Models\Group;
use App\Models\Strategy;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Strategy> */
class StrategyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => StrategyType::Individual->value,
            'student_id' => Student::factory(),
            'group_id' => null,
            'follow_up_id' => FollowUp::factory(),
            'body' => fake()->sentence(10),
            'responsible' => fake()->name(),
            'author_id' => User::factory(),
            'is_active' => true,
        ];
    }

    public function group(): static
    {
        return $this->state(fn () => [
            'type' => StrategyType::Group->value,
            'student_id' => null,
            'group_id' => Group::factory(),
        ]);
    }
}
