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
            self::Psicologia => 'Departamento de Apoyo',
            self::Emc => 'EMC (Coordinación)',
        };
    }

    /**
     * Indica si este rol corresponde al nombre guardado en la base de datos.
     *
     * El nombre del rol en la tabla compartida `roles` se usa como identidad y
     * puede renombrarse desde la intranet, por lo que se aceptan alias conocidos
     * (p. ej. "psicologia" se renombró a "Departamento de Apoyo") sin distinguir
     * mayúsculas ni espacios sobrantes.
     */
    public function matches(string $roleName): bool
    {
        $name = mb_strtolower(trim($roleName));

        if ($name === mb_strtolower($this->value) || $name === mb_strtolower($this->label())) {
            return true;
        }

        return match ($this) {
            self::Psicologia => in_array($name, ['psicologia', 'departamento de apoyo', 'departamento apoyo', 'dpto de apoyo', 'dpto. de apoyo'], true),
            self::Emc => in_array($name, ['emc', 'coordinacion', 'coordinación'], true),
            self::Profesor => in_array($name, ['profesor', 'profesora', 'docente'], true),
            self::Admin => in_array($name, ['admin', 'administrador', 'administradora'], true),
        };
    }

    /** Resuelve la etiqueta legible de un rol a partir de su nombre en la base de datos, sin lanzar excepción. */
    public static function labelFor(string $roleName): string
    {
        foreach (self::cases() as $case) {
            if ($case->matches($roleName)) {
                return $case->label();
            }
        }

        return ucfirst(trim($roleName));
    }
}
