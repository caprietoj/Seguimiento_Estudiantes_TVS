<?php

namespace App\Enums;

/** Los cuatro roles de spatie/laravel-permission. La dirección de grupo NO es un rol: es una asignación (groups.director_id). */
enum RoleName: string
{
    case Admin = 'admin';
    case Profesor = 'profesor';
    case Psicologia = 'psicologia';
    case Emc = 'emc';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Profesor => 'Profesor',
            self::Psicologia => 'Psicología',
            self::Emc => 'EMC (Coordinación)',
        };
    }
}
