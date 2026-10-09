<?php

namespace App\Enums;

/** Tipos de situación según la Ley 1620 de 2013 y el Manual de Convivencia. */
enum DisciplinaryCaseType: string
{
    case TypeI = 'type_i';
    case TypeII = 'type_ii';
    case TypeIII = 'type_iii';

    public function label(): string
    {
        return match ($this) {
            self::TypeI => 'Tipo I',
            self::TypeII => 'Tipo II',
            self::TypeIII => 'Tipo III',
        };
    }
}
