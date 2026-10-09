<?php

namespace App\Enums;

enum LoginMethod: string
{
    case Local = 'local';
    case Google = 'google';
    case Intranet = 'intranet';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Local',
            self::Google => 'Google',
            self::Intranet => 'Intranet institucional',
        };
    }
}
