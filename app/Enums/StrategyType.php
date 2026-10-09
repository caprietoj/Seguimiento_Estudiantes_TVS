<?php

namespace App\Enums;

enum StrategyType: string
{
    case Individual = 'individual';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Group => 'Grupal',
        };
    }
}
