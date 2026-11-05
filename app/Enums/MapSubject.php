<?php

namespace App\Enums;

enum MapSubject: string
{
    case Mathematics = 'math';
    case Reading = 'reading';
    case Language = 'language';
    case Science = 'science';

    public function label(): string
    {
        return match ($this) {
            self::Mathematics => 'Matemáticas (Math)',
            self::Reading => 'Lectura (Reading)',
            self::Language => 'Uso del Lenguaje (Language Usage)',
            self::Science => 'Ciencias (Science)',
        };
    }
}
