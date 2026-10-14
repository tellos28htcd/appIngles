<?php

namespace App\Enums;

enum SurveyQuestionType: string
{
    case Choice = 'choice';
    case Text = 'text';

    public function label(): string
    {
        return __("students.survey_types.{$this->value}");
    }
}
