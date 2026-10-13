<?php

namespace App\Support;

use App\Enums\ContributionField;
use App\Enums\RoleName;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Reglas de alcance y permisos de la sección 4 del encargo, centralizadas en un solo
 * lugar (como pide esa sección) para que Policies, controladores y props de Inertia
 * consulten siempre la misma fuente. Equivale al objeto `P` del boceto aprobado
 * (seguimiento-estudiantes.html), adaptado a los cuatro roles reales (no hay "director"
 * como rol: es la asignación groups.director_id) y a la bandera de configuración
 * seguimiento.emc_escribe_notas_clinicas.
 */
class Scope
{
    /**
     * Caché por request de las asignaciones docentes. Sin esto, `teaches()` y
     * `gradeWrite()` disparan un `exists` a `teaching_assignments` en cada llamada
     * (una por celda/columna en la reunión de grupo), lo que produce cientos de
     * consultas por página. En php-fpm los estáticos se reinician en cada request;
     * `flush()` permite limpiarlos (pruebas, Octane o cambios de asignación).
     *
     * @var array<int, array<int, bool>> group_id => true por user_id
     */
    private static array $teachingGroupIds = [];

    /** @var array<int, array<string, bool>> "group_id|subject_id" => true por user_id */
    private static array $teachingSubjectKeys = [];

    public static function flush(): void
    {
        self::$teachingGroupIds = [];
        self::$teachingSubjectKeys = [];
    }

    /** @return array<int, bool> Ids de grupo donde el usuario tiene alguna asignación. */
    private static function teachingGroupIds(User $user): array
    {
        return self::$teachingGroupIds[$user->id]
            ??= TeachingAssignment::query()
                ->where('user_id', $user->id)
                ->pluck('group_id')
                ->mapWithKeys(fn (mixed $id) => [(int) $id => true])
                ->all();
    }

    /** @return array<string, bool> Pares "group_id|subject_id" que el usuario enseña. */
    private static function teachingSubjectKeys(User $user): array
    {
        return self::$teachingSubjectKeys[$user->id]
            ??= TeachingAssignment::query()
                ->where('user_id', $user->id)
                ->get(['group_id', 'subject_id'])
                ->mapWithKeys(fn (TeachingAssignment $a) => [$a->group_id.'|'.$a->subject_id => true])
                ->all();
    }

    public static function isAdmin(User $user): bool
    {
        return self::hasRole($user, RoleName::Admin);
    }

    public static function isPsicologia(User $user): bool
    {
        return self::hasRole($user, RoleName::Psicologia);
    }

    /**
     * Compara el rol asignado tolerando que el rol se haya renombrado en la tabla
     * compartida `roles` (p. ej. "psicologia" → "Departamento de Apoyo").
     */
    private static function hasRole(User $user, RoleName $role): bool
    {
        return $user->roles->pluck('name')->contains(fn (mixed $name) => is_string($name) && $role->matches($name));
    }

    public static function hasRoleName(User $user, RoleName $role): bool
    {
        return self::hasRole($user, $role);
    }

    public static function isDirector(User $user, Group $group): bool
    {
        return $group->director_id === $user->id;
    }

    public static function teaches(User $user, Group $group): bool
    {
        if (! self::hasRole($user, RoleName::Profesor)) {
            return false;
        }

        return isset(self::teachingGroupIds($user)[$group->id]);
    }

    /** EMC cuyo alcance cubre la sección del grupo: sin sección asignada ve todo el colegio. */
    public static function emcInSection(User $user, Group $group): bool
    {
        if (! self::hasRole($user, RoleName::Emc)) {
            return false;
        }

        if (! config('seguimiento.emc_limita_por_seccion', true)) {
            return true;
        }

        return $user->emc_section_id === null || $user->emc_section_id === $group->section_id;
    }

    /** Puede ver/trabajar con este grupo: admin, psicología, EMC de su sección, o profesor/director del grupo. */
    public static function inScope(User $user, Group $group): bool
    {
        return self::isAdmin($user)
            || self::isPsicologia($user)
            || self::emcInSection($user, $group)
            || self::isDirector($user, $group)
            || self::teaches($user, $group);
    }

    /** Ve las notas clínicas (Psicología, Fonoaudiología, T.O., Neuropsicología). */
    public static function clinicalSee(User $user, Group $group): bool
    {
        return self::isAdmin($user) || self::isPsicologia($user) || self::emcInSection($user, $group);
    }

    public static function clinicalWrite(User $user, Group $group): bool
    {
        if (self::isAdmin($user) || self::isPsicologia($user)) {
            return true;
        }

        return config('seguimiento.emc_escribe_notas_clinicas', false) && self::emcInSection($user, $group);
    }

    /** Ve la nota de Coordinación: también el director de grupo (no ve las clínicas). */
    public static function coordinationSee(User $user, Group $group): bool
    {
        return self::clinicalSee($user, $group) || self::isDirector($user, $group);
    }

    public static function coordinationWrite(User $user, Group $group): bool
    {
        return self::isAdmin($user) || self::emcInSection($user, $group);
    }

    public static function seesField(User $user, Group $group, ContributionField $field): bool
    {
        if (! $field->isSupportArea()) {
            return true;
        }

        return $field === ContributionField::SupportCoordination
            ? self::coordinationSee($user, $group)
            : self::clinicalSee($user, $group);
    }

    public static function writesField(User $user, Group $group, ContributionField $field): bool
    {
        if (! self::inScope($user, $group)) {
            return false;
        }

        if (! $field->isSupportArea()) {
            return true;
        }

        return $field === ContributionField::SupportCoordination
            ? self::coordinationWrite($user, $group)
            : self::clinicalWrite($user, $group);
    }

    /** Estrategias/aportes/compromisos propios o del alcance de quien los creó. */
    public static function canDelete(User $user, Group $group, ?int $authorId): bool
    {
        return $authorId === $user->id
            || self::isAdmin($user)
            || self::isPsicologia($user)
            || self::emcInSection($user, $group)
            || self::isDirector($user, $group);
    }

    /** Crear o editar el seguimiento de estrategias grupales. */
    public static function canManageGroupStrategy(User $user, Group $group): bool
    {
        return self::isAdmin($user) || self::isPsicologia($user) || self::emcInSection($user, $group) || self::isDirector($user, $group);
    }

    public static function externalSupportsSee(User $user, Group $group): bool
    {
        return self::clinicalSee($user, $group);
    }

    public static function disciplinarySee(User $user, Group $group): bool
    {
        return self::clinicalSee($user, $group) || self::isDirector($user, $group);
    }

    public static function disciplinaryWrite(User $user, Group $group): bool
    {
        return self::isAdmin($user) || self::emcInSection($user, $group) || self::isDirector($user, $group);
    }

    public static function committeeWrite(User $user, Group $group): bool
    {
        return self::isAdmin($user) || self::isPsicologia($user) || self::emcInSection($user, $group) || self::isDirector($user, $group);
    }

    public static function photoWrite(User $user, Group $group): bool
    {
        return self::isAdmin($user) || self::emcInSection($user, $group) || self::isDirector($user, $group);
    }

    /** Puede registrar/editar el nivel de una asignatura para un grupo: su propia asignatura si la enseña ahí, EMC o admin. */
    public static function gradeWrite(User $user, Group $group, Subject $subject): bool
    {
        if (self::isAdmin($user) || self::emcInSection($user, $group)) {
            return true;
        }

        return isset(self::teachingSubjectKeys($user)[$group->id.'|'.$subject->id]);
    }

    /** @return Collection<int, Group> Grupos que el usuario puede ver, del año escolar dado. */
    public static function visibleGroups(User $user, int $schoolYearId)
    {
        return Group::query()
            ->where('school_year_id', $schoolYearId)
            ->with('section')
            ->get()
            ->filter(fn (Group $group) => self::inScope($user, $group))
            ->values();
    }

    public static function canViewStudent(User $user, Student $student): bool
    {
        $group = $student->currentGroup();

        return $group !== null && self::inScope($user, $group);
    }
}
