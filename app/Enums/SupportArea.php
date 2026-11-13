<?php

namespace App\Enums;

use App\Support\Import\NameMatcher;

/**
 * Áreas del Departamento de Apoyo para el registro de actividades y la estadística general.
 * "Equipo" son las acciones del departamento completo (encuentros con padres, capacitaciones…).
 */
enum SupportArea: string
{
    case Psychology = 'psychology';
    case Ot = 'ot';
    case Speech = 'speech';
    case Neuro = 'neuro';
    case Team = 'team';

    public function label(): string
    {
        return match ($this) {
            self::Psychology => 'Psicología',
            self::Ot => 'Terapia ocupacional',
            self::Speech => 'Fonoaudiología',
            self::Neuro => 'Neuropsicología',
            self::Team => 'Todo el departamento',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Psychology => 'Psicología',
            self::Ot => 'T.O.',
            self::Speech => 'Fono',
            self::Neuro => 'Neuro',
            self::Team => 'Equipo',
        };
    }

    /** Campo de aporte de la ficha que corresponde al área (el equipo no tiene uno propio). */
    public function contributionField(): ?ContributionField
    {
        return match ($this) {
            self::Psychology => ContributionField::SupportPsychology,
            self::Ot => ContributionField::SupportOt,
            self::Speech => ContributionField::SupportSpeech,
            self::Neuro => ContributionField::SupportNeuro,
            self::Team => null,
        };
    }

    /**
     * Áreas que tienen columna propia en las tablas por grado (como la hoja «ESTADÍSTICA GRAL»).
     *
     * @return list<self>
     */
    public static function clinical(): array
    {
        return [self::Psychology, self::Ot, self::Speech, self::Neuro];
    }

    /** Reconoce el área tal como la escribe el colegio («PSICOLOGIA Kº A 3», «T.O», «FONOAUDIOLOGíA»…). */
    public static function fromText(?string $text): ?self
    {
        $t = NameMatcher::normalize((string) $text);

        if ($t === '') {
            return null;
        }

        return match (true) {
            str_contains($t, 'neuro') => self::Neuro,
            str_contains($t, 'psico') => self::Psychology,
            str_contains($t, 'fono') => self::Speech,
            str_contains($t, 'terapia') || str_contains($t, 'ocupacional') || in_array(str_replace(' ', '', $t), ['to', 't o'], true) => self::Ot,
            str_contains($t, 'equipo') || str_contains($t, 'departamento') || str_contains($t, 'todas') => self::Team,
            default => null,
        };
    }
}
