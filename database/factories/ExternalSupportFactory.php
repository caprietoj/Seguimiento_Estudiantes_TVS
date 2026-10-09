<?php

namespace Database\Factories;

use App\Models\ExternalSupport;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExternalSupport> */
class ExternalSupportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'provider' => fake()->company(),
            'specialty' => fake()->randomElement(['Terapia ocupacional', 'Neuropediatría', 'Fonoaudiología', 'Psicología clínica']),
            'frequency' => fake()->randomElement(['Semanal', 'Quincenal', 'Mensual', 'Trimestral']),
            'contact' => 'A través de la familia',
            'notes' => fake()->optional()->sentence(10),
            'author_id' => User::factory(),
        ];
    }
}
