<?php

namespace App\Enums;

enum ChargeConceptType: string
{
    case Tuition = 'tuition';
    case Enrollment = 'enrollment';
    case Material = 'material';
    case ExtraActivity = 'extra_activity';
    case Extension = 'extension';
    case LateFee = 'late_fee';
    case Other = 'other';

    public function label(): string
    {
        return __("settings.concept_types.{$this->value}");
    }
}
