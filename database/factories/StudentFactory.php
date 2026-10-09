<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institutional_code' => fake()->unique()->numerify('TVS-#####'),
            'full_name' => fake()->name(),
            'photo_path' => null,
            'active' => true,
        ];
    }
}
