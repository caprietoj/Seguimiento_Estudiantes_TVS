<?php

namespace App\Enums;

enum ImportTemplateType: string
{
    case CurrentFormat = 'current_format';
    case Users = 'users';
    case StudentsEnrollments = 'students_enrollments';
    case PhidiasStudents = 'phidias_students';
    case Groups = 'groups';
    case TeachingAssignments = 'teaching_assignments';
    case Grades = 'grades';
    case Contributions = 'contributions';
    case Strategies = 'strategies';
    case Commitments = 'commitments';
    case ExternalSupports = 'external_supports';
    case CommitteeDecisions = 'committee_decisions';
    case DisciplinaryCases = 'disciplinary_cases';
    case MapScores = 'map_scores';

    public function label(): string
    {
        return match ($this) {
            self::CurrentFormat => 'Formato actual (Seguimiento y acuerdos)',
            self::Users => 'Usuarios',
            self::StudentsEnrollments => 'Estudiantes y matrículas por año',
            self::PhidiasStudents => 'Estudiantes desde Phidias',
            self::Groups => 'Grupos y dirección de grupo',
            self::TeachingAssignments => 'Asignaciones de profesores',
            self::Grades => 'Calificaciones de asignaturas (IB)',
            self::MapScores => 'Calificaciones MAP Growth (NWEA)',
            self::Contributions => 'Aportes',
            self::Strategies => 'Estrategias',
            self::Commitments => 'Compromisos',
            self::ExternalSupports => 'Apoyos externos',
            self::CommitteeDecisions => 'Comité evaluador',
            self::DisciplinaryCases => 'Proceso disciplinario',
        };
    }
}
