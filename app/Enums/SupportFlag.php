<?php

namespace App\Enums;

/**
 * Marcas Sí/No de una nota del Dpto. de Apoyo en la reunión de grupo. Cada una es una columna
 * booleana de `contributions` y alimenta un indicador de la estadística general.
 */
enum SupportFlag: string
{
    case InFollowUp = 'in_follow_up';
    case FamilyAttended = 'family_attended';
    case PiarMeeting = 'piar_meeting';
    case CrisisAttention = 'crisis_attention';

    public function label(): string
    {
        return match ($this) {
            self::InFollowUp => 'Estudiante en seguimiento del área',
            self::FamilyAttended => 'Se atendió a la familia',
            self::PiarMeeting => 'Reunión PIAR',
            self::CrisisAttention => 'Atención en crisis',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::InFollowUp => 'En seguimiento',
            self::FamilyAttended => 'Familia atendida',
            self::PiarMeeting => 'Reunión PIAR',
            self::CrisisAttention => 'Atención en crisis',
        };
    }

    /** @return list<string> */
    public static function columns(): array
    {
        return array_map(fn (self $f) => $f->value, self::cases());
    }
}
