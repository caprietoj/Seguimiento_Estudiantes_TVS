<?php

namespace App\Enums;

enum StrategyResult: string
{
    case Works = 'works';
    case Partially = 'partially';
    case DoesNotWork = 'does_not_work';

    public function label(): string
    {
        return match ($this) {
            self::Works => 'Funciona',
            self::Partially => 'Funciona parcialmente',
            self::DoesNotWork => 'No funciona',
        };
    }
}
