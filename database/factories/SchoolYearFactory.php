<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SchoolYear> */
class SchoolYearFactory extends Factory
{
    public function definition(): array
    {
        $startYear = fake()->numberBetween(2023, 2026);

        return [
            'name' => "{$startYear}–".($startYear + 1),
            'starts_on' => "{$startYear}-08-01",
            'ends_on' => ($startYear + 1).'-06-15',
            'is_active' => false,
            'is_read_only' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true, 'is_read_only' => false]);
    }

    public function readOnly(): static
    {
        return $this->state(['is_active' => false, 'is_read_only' => true]);
    }
}
