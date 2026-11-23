<?php

namespace App\Enums;

use App\Support\Import\NameMatcher;

enum ContributionField: string
{
    case Strength = 'strength';
    case Improvement = 'improvement';
    case Observation = 'observation';
    case Conclusion = 'conclusion';
    case SupportPsychology = 'support_psychology';
    case SupportSpeech = 'support_speech';
    case SupportOt = 'support_ot';
    case SupportNeuro = 'support_neuro';
    case SupportCoordination = 'support_coordination';
    case SupportTutoring = 'support_tutoring';
    case SupportOther = 'support_other';
    case SubjectAttention = 'subject_attention';

    public function label(): string
    {
        return match ($this) {
            self::Strength => 'Fortalezas / avances',
            self::Improvement => 'Aspectos de mejora',
            self::Observation => 'Observación',
            self::Conclusion => 'Conclusión',
            self::SubjectAttention => 'Asignaturas para tener en cuenta',
            self::SupportPsychology => 'Psicología',
            self::SupportSpeech => 'Fonoaudiología',
            self::SupportOt => 'Terapia ocupacional',
            self::SupportNeuro => 'Neuropsicología',
            self::SupportCoordination => 'Coordinación',
            self::SupportTutoring => 'Tutorías',
            self::SupportOther => 'Otros',
        };
    }

    /** Campos del Dpto. de apoyo / Grupo focal cuyas notas son clínicas (las escribe solo Psicología). */
    public function isClinical(): bool
    {
        return in_array($this, [
            self::SupportPsychology,
            self::SupportSpeech,
            self::SupportOt,
            self::SupportNeuro,
        ], true);
    }

    /** @return list<ContributionField> Áreas del Dpto. de Apoyo con notas en formato del colegio y estadística. */
    public static function clinical(): array
    {
        return [self::SupportPsychology, self::SupportOt, self::SupportSpeech, self::SupportNeuro];
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::SupportOt => 'T.O.',
            self::SupportSpeech => 'Fono',
            self::SupportNeuro => 'Neuro',
            default => $this->label(),
        };
    }

    /** Área del Dpto. tal como la escribe el colegio («PSICOLOGIA Kº A 3», «T.O», «FONOAUDIOLOGíA»…). */
    public static function clinicalFromText(?string $text): ?self
    {
        $t = NameMatcher::normalize((string) $text);

        return match (true) {
            $t === '' => null,
            str_contains($t, 'neuro') => self::SupportNeuro,
            str_contains($t, 'psico') => self::SupportPsychology,
            str_contains($t, 'fono') => self::SupportSpeech,
            str_contains($t, 'terapia') || str_contains($t, 'ocupacional') || str_replace(' ', '', $t) === 'to' => self::SupportOt,
            default => null,
        };
    }

    public function isSupportArea(): bool
    {
        return in_array($this, self::supportAreas(), true);
    }

    /** @return ContributionField[] */
    public static function supportAreas(): array
    {
        return [
            self::SupportPsychology,
            self::SupportSpeech,
            self::SupportOt,
            self::SupportNeuro,
            self::SupportCoordination,
            self::SupportTutoring,
            self::SupportOther,
        ];
    }
}
