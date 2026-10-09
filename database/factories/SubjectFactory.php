<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subject> */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Lengua y Literatura', 'Inglés (Adq. de Lenguas)', 'Francés',
                'Individuos y Sociedades', 'Matemáticas', 'Ciencias', 'Artes',
                'Diseño', 'Educación Física',
            ]),
            'active' => true,
        ];
    }
}
