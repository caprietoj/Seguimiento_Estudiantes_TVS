<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use Illuminate\Database\Seeder;

/**
 * Catálogo base de grados: Prekinder (0), Kinder (1), Transición (2) y 1.° a 11.°
 * (3 a 13). Es el mismo catálogo que siembra la migración create_grade_levels_table,
 * extraído aquí para poder repoblarlo por separado (por ejemplo, tras un reinicio de
 * datos) sin correr el resto de seeders. Es idempotente: usa `updateOrCreate` por `value`.
 */
class GradeLevelSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            0 => 'Prekinder', 1 => 'Kinder', 2 => 'Transición',
            3 => '1.°', 4 => '2.°', 5 => '3.°', 6 => '4.°', 7 => '5.°',
            8 => '6.°', 9 => '7.°', 10 => '8.°', 11 => '9.°', 12 => '10.°', 13 => '11.°',
        ];

        foreach ($names as $value => $name) {
            GradeLevel::query()->updateOrCreate(
                ['value' => $value],
                ['name' => $name, 'active' => true],
            );
        }
    }
}
