<?php

namespace App\Enums;

enum CommitmentType: string
{
    case School = 'school';
    case Family = 'family';

    public function label(): string
    {
        return match ($this) {
            self::School => 'Del colegio',
            self::Family => 'De la familia',
        };
    }
}
