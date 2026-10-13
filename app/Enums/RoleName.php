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
        return in_array(mb_strtolower(trim($roleName)), $this->aliases(), true);
    }

    /**
     * Nombres (en minúscula) con los que este rol puede aparecer en la tabla compartida `roles`.
     *
     * @return list<string>
     */
    public function aliases(): array
    {
        $known = match ($this) {
            self::Psicologia => ['psicologia', 'psicología', 'departamento de apoyo', 'departamento apoyo', 'dpto de apoyo', 'dpto. de apoyo'],
            self::Emc => ['emc', 'coordinacion', 'coordinación'],
            self::Profesor => ['profesor', 'profesora', 'docente', 'docentes', 'profesores'],
            self::Admin => ['admin', 'administrador', 'administradora'],
        };

        return array_values(array_unique([mb_strtolower($this->value), mb_strtolower($this->label()), ...$known]));
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
