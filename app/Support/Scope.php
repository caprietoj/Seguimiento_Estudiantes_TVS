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
    public static function isAdmin(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    public static function isPsicologia(User $user): bool
    {
        return $user->hasRole(RoleName::Psicologia->value);
    }

    public static function isDirector(User $user, Group $group): bool
    {
        return $group->director_id === $user->id;
    }

    public static function teaches(User $user, Group $group): bool
    {
        if (! $user->hasRole(RoleName::Profesor->value)) {
            return false;
        }

        return TeachingAssignment::query()
            ->where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->exists();
    }

    /** EMC cuyo alcance cubre la sección del grupo: sin sección asignada ve todo el colegio. */
    public static function emcInSection(User $user, Group $group): bool
    {
        if (! $user->hasRole(RoleName::Emc->value)) {
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

        return TeachingAssignment::query()
            ->where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->where('subject_id', $subject->id)
            ->exists();
    }

    /** @return Collection<int, Group> Grupos que el usuario puede ver, del año escolar dado. */
    public static function visibleGroups(User $user, int $schoolYearId)
    {
        return Group::query()
            ->where('school_year_id', $schoolYearId)
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
