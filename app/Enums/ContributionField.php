<?php

namespace App\Enums;

enum ContributionField: string
{
    case Strength = 'strength';
    case Improvement = 'improvement';
    case Observation = 'observation';
    case SupportPsychology = 'support_psychology';
    case SupportSpeech = 'support_speech';
    case SupportOt = 'support_ot';
    case SupportNeuro = 'support_neuro';
    case SupportCoordination = 'support_coordination';
    case SubjectAttention = 'subject_attention';

    public function label(): string
    {
        return match ($this) {
            self::Strength => 'Fortalezas / avances',
            self::Improvement => 'Aspectos de mejora',
            self::Observation => 'Observación',
            self::SubjectAttention => 'Asignaturas para tener en cuenta',
            self::SupportPsychology => 'Psicología',
            self::SupportSpeech => 'Fonoaudiología',
            self::SupportOt => 'Terapia ocupacional',
            self::SupportNeuro => 'Neuropsicología',
            self::SupportCoordination => 'Coordinación',
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

    public function isSupportArea(): bool
    {
        return $this->isClinical() || $this === self::SupportCoordination;
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
        ];
    }
}
