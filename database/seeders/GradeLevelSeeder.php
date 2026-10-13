<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use Illuminate\Database\Seeder;

/**
 * Catálogo base de grados (Preescolar = 0 a 11.°). Es el mismo catálogo que
 * siembra la migración create_grade_levels_table, extraído aquí para poder
 * repoblarlo por separado (por ejemplo, tras un reinicio de datos) sin correr
 * el resto de seeders. Es idempotente: usa `updateOrCreate` por `value`.
 */
class GradeLevelSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            0 => 'Preescolar', 1 => '1.°', 2 => '2.°', 3 => '3.°', 4 => '4.°',
            5 => '5.°', 6 => '6.°', 7 => '7.°', 8 => '8.°', 9 => '9.°',
            10 => '10.°', 11 => '11.°',
        ];

        foreach ($names as $value => $name) {
            GradeLevel::query()->updateOrCreate(
                ['value' => $value],
                ['name' => $name, 'active' => true],
            );
        }
    }
}
