<?php

namespace App\Enums;

enum CommitteeDecision: string
{
    case ContinueFollowUp = 'continue_follow_up';
    case ImprovementPlan = 'improvement_plan';
    case ReferralSupport = 'referral_support';
    case FamilyMeeting = 'family_meeting';
    case NoNews = 'no_news';

    public function label(): string
    {
        return match ($this) {
            self::ContinueFollowUp => 'Continúa seguimiento',
            self::ImprovementPlan => 'Plan de mejoramiento',
            self::ReferralSupport => 'Remisión a Dpto. de apoyo',
            self::FamilyMeeting => 'Citación a la familia',
            self::NoNews => 'Sin novedad',
        };
    }
}
