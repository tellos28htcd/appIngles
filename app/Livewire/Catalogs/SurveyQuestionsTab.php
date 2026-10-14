<?php

namespace App\Livewire\Catalogs;

use App\Enums\SurveyQuestionType;
use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\SurveyQuestion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Pestaña "Encuesta": preguntas del alumno por sección (opción de una lista o texto libre). */
class SurveyQuestionsTab extends Component
{
    use ManagesCatalog;

    public ?bool $editing = null;

    public ?int $questionId = null;

    public string $section = '';

    public string $question = '';

    public string $type = 'choice';

    /** Una opción por renglón. */
    public string $options = '';

    public int $sortOrder = 10;

    public function mount(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
        $this->authorizeCatalog();
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $record = $id ? $this->findInCatalog(SurveyQuestion::class, $id) : null;
        $this->questionId = $record?->id;
        $this->section = (string) ($record?->section ?? $this->questions->last()?->section);
        $this->question = (string) $record?->question;
        $this->type = $record?->type->value ?? SurveyQuestionType::Choice->value;
        $this->options = implode("\n", $record?->options ?? []);
        $this->sortOrder = $record?->sort_order ?? (int) $this->questions->max('sort_order') + 10;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();

        $options = collect(preg_split('/\R/', $this->options) ?: [])->map(fn (string $option) => trim($option))->filter()->unique()->values()->all();

        $this->validate([
            'section' => ['required', 'string', 'max:80'],
            'question' => ['required', 'string', 'max:255', $this->uniqueInCatalog('survey_questions', 'question', $this->questionId)],
            'type' => ['required', Rule::enum(SurveyQuestionType::class)],
            'options' => [Rule::requiredIf($this->type === SurveyQuestionType::Choice->value), 'nullable', 'string', 'max:3000'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
        ], [], collect(['section', 'question', 'type', 'options', 'sortOrder'])
            ->mapWithKeys(fn (string $field) => [$field => Str::lower(__('catalogs.student.survey.fields.'.Str::snake($field)))])
            ->all());

        $record = $this->questionId
            ? $this->findInCatalog(SurveyQuestion::class, $this->questionId)
            : new SurveyQuestion(['school_id' => $this->schoolId, 'is_active' => true]);

        $record->fill([
            'section' => trim($this->section),
            'question' => trim($this->question),
            'type' => $this->type,
            'options' => $this->type === SurveyQuestionType::Choice->value ? $options : null,
            'sort_order' => $this->sortOrder,
        ])->save();

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('catalogs.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(SurveyQuestion::class, $id);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion(SurveyQuestion::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['SurveyQuestion' => SurveyQuestion::class]);
    }

    /** @return Collection<int, SurveyQuestion> */
    #[Computed]
    public function questions(): Collection
    {
        return $this->catalog(SurveyQuestion::class)->orderBy('sort_order')->orderBy('id')->get();
    }

    public function render(): View
    {
        return view('livewire.catalogs.survey-questions-tab', [
            'types' => collect(SurveyQuestionType::cases())->mapWithKeys(fn (SurveyQuestionType $type) => [$type->value => $type->label()])->all(),
            'sections' => $this->questions->pluck('section')->unique()->values()->all(),
        ]);
    }
}
