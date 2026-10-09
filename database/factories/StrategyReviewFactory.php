<?php

namespace Database\Factories;

use App\Enums\StrategyResult;
use App\Models\Strategy;
use App\Models\StrategyReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StrategyReview> */
class StrategyReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'strategy_id' => Strategy::factory(),
            'reviewed_on' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'body' => fake()->sentence(8),
            'result' => fake()->randomElement(StrategyResult::cases())->value,
            'author_id' => User::factory(),
        ];
    }
}
