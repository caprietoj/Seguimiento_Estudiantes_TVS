<?php

namespace App\Enums;

enum DisciplinaryCaseStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Closed => 'Cerrado',
        };
    }
}
