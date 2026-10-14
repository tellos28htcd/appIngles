<?php

namespace App\Models;

use App\Enums\SurveyQuestionType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Pregunta de la encuesta del alumno (opción de una lista o texto libre). */
#[Fillable(['school_id', 'base_id', 'section', 'question', 'type', 'options', 'sort_order', 'is_active'])]
class SurveyQuestion extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'type' => SurveyQuestionType::class,
            'options' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function auditLabel(): string
    {
        return $this->question;
    }

    /** Con respuestas ya no se elimina: se desactiva para conservarlas. */
    public function canBeDeleted(): bool
    {
        return $this->school_id === null || ! StudentSurveyAnswer::withoutGlobalScope('school')->where('survey_question_id', $this->id)->exists();
    }
}
