<?php

namespace App\Enums;

use App\Support\Import\NameMatcher;

/**
 * Tipos de actividad del Dpto. de Apoyo. Los cinco primeros son los indicadores de la hoja
 * «ESTADÍSTICA GRAL» del informe de gestión; el resto alimenta el resumen de otras actividades.
 */
enum SupportActivityType: string
{
    case StudentCase = 'student_case';
    case Family = 'family';
    case FollowUpMeeting = 'follow_up_meeting';
    case Piar = 'piar';
    case Crisis = 'crisis';
    case Admission = 'admission';
    case CoexistenceCommittee = 'coexistence_committee';
    case EvaluationCommittee = 'evaluation_committee';
    case LevelMeeting = 'level_meeting';
    case ClassObservation = 'class_observation';
    case Workshop = 'workshop';
    case RouteActivation = 'route_activation';
    case ParentMeeting = 'parent_meeting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::StudentCase => 'Estudiante en seguimiento',
            self::Family => 'Familia atendida',
            self::FollowUpMeeting => 'Reunión de seguimiento por curso',
            self::Piar => 'Reunión PIAR',
            self::Crisis => 'Intervención en crisis',
            self::Admission => 'Admisión',
            self::CoexistenceCommittee => 'Comité de convivencia',
            self::EvaluationCommittee => 'Comité de evaluación y promoción',
            self::LevelMeeting => 'Reunión de nivel',
            self::ClassObservation => 'Observación de clase',
            self::Workshop => 'Taller o programa',
            self::RouteActivation => 'Activación de ruta / grupo de protección',
            self::ParentMeeting => 'Encuentro o reunión con padres',
            self::Other => 'Otra actividad',
        };
    }

    /** Título de la tabla en la estadística general (como en el informe). */
    public function indicatorTitle(): string
    {
        return match ($this) {
            self::StudentCase => 'Estudiantes con seguimiento por área',
            self::Family => 'Familias atendidas por área',
            self::FollowUpMeeting => 'Reuniones de seguimiento por área',
            self::Piar => 'Reuniones de PIAR',
            self::Crisis => 'Atención en crisis',
            default => $this->label(),
        };
    }

    /** @return list<self> Los cinco indicadores de la estadística general, en el orden del informe. */
    public static function indicators(): array
    {
        return [self::StudentCase, self::Family, self::FollowUpMeeting, self::Piar, self::Crisis];
    }

    public function isIndicator(): bool
    {
        return in_array($this, self::indicators(), true);
    }

    /** Un caso de seguimiento es de un estudiante concreto (o una cantidad agregada sin nombres). */
    public function isPerStudent(): bool
    {
        return $this === self::StudentCase;
    }

    /** Reconoce el tipo a partir del texto del informe o de la plantilla. */
    public static function fromText(?string $text): ?self
    {
        $t = NameMatcher::normalize((string) $text);

        if ($t === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($t === NameMatcher::normalize($case->label()) || $t === $case->value) {
                return $case;
            }
        }

        return match (true) {
            str_contains($t, 'estudiante') && str_contains($t, 'seguimiento'), str_contains($t, 'caso') => self::StudentCase,
            str_contains($t, 'familia') => self::Family,
            str_contains($t, 'piar') => self::Piar,
            str_contains($t, 'crisis') => self::Crisis,
            str_contains($t, 'seguimiento') && str_contains($t, 'reunion') => self::FollowUpMeeting,
            str_contains($t, 'admision') => self::Admission,
            str_contains($t, 'convivencia') && str_contains($t, 'comite') => self::CoexistenceCommittee,
            str_contains($t, 'comite') && (str_contains($t, 'evaluacion') || str_contains($t, 'promocion')) => self::EvaluationCommittee,
            str_contains($t, 'nivel') => self::LevelMeeting,
            str_contains($t, 'observacion') => self::ClassObservation,
            str_contains($t, 'ruta') || str_contains($t, 'proteccion') => self::RouteActivation,
            str_contains($t, 'padres') => self::ParentMeeting,
            str_contains($t, 'taller') || str_contains($t, 'programa') || str_contains($t, 'grupos focales') || str_contains($t, 'capacitacion') => self::Workshop,
            default => null,
        };
    }
}
