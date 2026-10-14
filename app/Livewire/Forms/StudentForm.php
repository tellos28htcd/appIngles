<?php

namespace App\Livewire\Forms;

use App\Actions\Students\ChangeStudentStatus;
use App\Actions\Students\EnrollmentNumbers;
use App\Actions\Students\SyncStudentUser;
use App\Enums\StudentStatus;
use App\Enums\SurveyQuestionType;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentSurveyAnswer;
use App\Models\SurveyQuestion;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Datos generales del alumno, su estilo de aprendizaje, foto y encuesta.
 * Con correo se crea (o actualiza) su usuario, sin enviarle correo.
 */
class StudentForm extends Form
{
    public ?Student $student = null;

    /** Vacía = se genera la siguiente de la escuela; se captura solo si viene de otra escuela de la franquicia. */
    public string $enrollment_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $second_last_name = '';

    public string $mobile_phone = '';

    public string $address = '';

    public string $email = '';

    public string $birth_date = '';

    public ?int $student_type_id = null;

    public ?int $information_source_id = null;

    public bool $advertising_consent = false;

    public bool $learns_visual = false;

    public bool $learns_auditory = false;

    public bool $learns_kinesthetic = false;

    public string $notes = '';

    /** Solo en el alta; después se cambia desde el expediente con su motivo. */
    public string $status = 'active';

    /** @var UploadedFile|null */
    public $photo = null;

    public bool $remove_photo = false;

    /** @var array<int|string, string> pregunta => respuesta */
    public array $answers = [];

    public function setStudent(Student $student): void
    {
        $this->student = $student;

        $this->fill([
            ...array_map(fn ($value) => (string) $value, $student->only([
                'enrollment_number', 'first_name', 'last_name', 'second_last_name', 'mobile_phone', 'address', 'email', 'notes',
            ])),
            'birth_date' => (string) $student->birth_date?->toDateString(),
            ...$student->only([
                'student_type_id', 'information_source_id', 'advertising_consent', 'learns_visual', 'learns_auditory', 'learns_kinesthetic',
            ]),
            'status' => $student->status->value,
            'answers' => $student->surveyAnswers()->pluck('answer', 'survey_question_id')->map(fn ($answer) => (string) $answer)->all(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $schoolId = auth()->user()->school_id;
        $inSchool = fn (string $table) => Rule::exists($table, 'id')->where('school_id', $schoolId);

        return [
            'enrollment_number' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('students', 'enrollment_number')->ignore($this->student),
            ],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'second_last_name' => ['nullable', 'string', 'max:80'],
            'mobile_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +()\-]{7,20}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => [
                // Si ya tiene usuario, su correo es su acceso y no se puede quitar.
                Rule::requiredIf(fn () => $this->student?->user_id !== null),
                'nullable', 'string', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($this->student?->user_id),
                Rule::unique('students', 'email')->where('school_id', $schoolId)->ignore($this->student),
            ],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'student_type_id' => ['nullable', 'integer', $inSchool('student_types')],
            'information_source_id' => ['nullable', 'integer', $inSchool('information_sources')],
            'advertising_consent' => ['boolean'],
            'learns_visual' => ['boolean'],
            'learns_auditory' => ['boolean'],
            'learns_kinesthetic' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'answers' => ['array'],
            'answers.*' => ['nullable', 'string', 'max:500', $this->validAnswer()],
        ];
    }

    /** Una respuesta de opción debe ser una de sus opciones; las preguntas deben ser de la escuela. */
    private function validAnswer(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $question = $this->questions()->get((int) Str::after($attribute, 'answers.'));

            if ($question === null) {
                $fail(__('students.validation.survey_question'));

                return;
            }

            // Una respuesta importada que no está en las opciones se conserva tal cual.
            $isStoredAnswer = $this->student !== null && StudentSurveyAnswer::where('student_id', $this->student->id)
                ->where('survey_question_id', $question->id)->where('answer', $value)->exists();

            if ($value !== null && $value !== '' && $question->type === SurveyQuestionType::Choice
                && ! in_array($value, $question->options ?? [], true) && ! $isStoredAnswer) {
                $fail(__('students.validation.survey_option'));
            }
        };
    }

    /** @return Collection<int, SurveyQuestion> */
    public function questions(): Collection
    {
        return SurveyQuestion::query()->ofCatalog(auth()->user()->school_id)->where('is_active', true)
            ->orderBy('sort_order')->get()->keyBy('id');
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'email.unique' => __('users.validation.email_taken'),
            'email.required' => __('students.validation.email_required'),
            'enrollment_number.unique' => __('students.validation.enrollment_number_taken'),
            'enrollment_number.regex' => __('students.validation.enrollment_number_format'),
            'mobile_phone.regex' => __('students.validation.phone'),
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return collect(array_keys($this->rules()))
            ->reject(fn (string $field) => str_contains($field, '*'))
            ->mapWithKeys(fn (string $field) => [$field => Str::lower(__("students.fields.$field"))])
            ->all();
    }

    public function normalize(): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->enrollment_number = Str::upper(preg_replace('/\s+/', '', $this->enrollment_number));
        $this->first_name = Str::upper(trim($this->first_name));
        $this->last_name = Str::upper(trim($this->last_name));
        $this->second_last_name = Str::upper(trim($this->second_last_name));
    }

    /** Valida sin guardar (el alta valida alumno e inscripción antes de escribir). */
    public function check(): void
    {
        $this->normalize();
        $this->validate();
    }

    /**
     * Guarda el alumno, su usuario (si tiene correo) y su encuesta.
     *
     * @param  (Closure(Student): void)|null  $alsoSave  lo que se guarda en la misma transacción (p. ej. su inscripción)
     */
    public function save(EnrollmentNumbers $numbers, ChangeStudentStatus $changeStatus, SyncStudentUser $syncUser, ?Closure $alsoSave = null): Student
    {
        $this->normalize();
        $data = $this->validate();
        $photo = $data['photo'] ?? null;
        $answers = $data['answers'];
        unset($data['photo'], $data['answers']);
        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);
        $creating = $this->student === null;
        $school = School::findOrFail(auth()->user()->school_id);

        $student = DB::transaction(function () use ($data, $answers, $creating, $school, $numbers, $changeStatus, $syncUser, $alsoSave): Student {
            $status = StudentStatus::from($data['status']);
            unset($data['status']);

            // La matrícula no cambia después del alta.
            if (! $creating) {
                $data['enrollment_number'] = $this->student->enrollment_number;
            } elseif ($data['enrollment_number'] === null) {
                $data['enrollment_number'] = $numbers->next($school);
            } else {
                $numbers->register($school, $data['enrollment_number']);
            }

            $student = $this->student ?? new Student(['school_id' => $school->id, 'status' => $status]);
            $student->fill($data)->save();

            $creating
                ? $changeStatus->handle($student, $status)
                : $syncUser->handle($student);

            $this->saveAnswers($student, $answers);

            if ($alsoSave !== null) {
                $alsoSave($student);
            }

            return $student;
        });

        $this->syncPhoto($student, $photo);
        $this->setStudent($student->refresh());
        $this->reset('photo', 'remove_photo');

        return $student;
    }

    /** @param array<int|string, string|null> $answers */
    private function saveAnswers(Student $student, array $answers): void
    {
        foreach ($answers as $questionId => $answer) {
            $answer = trim((string) $answer);

            if ($answer === '') {
                StudentSurveyAnswer::where('student_id', $student->id)->where('survey_question_id', $questionId)->delete();

                continue;
            }

            StudentSurveyAnswer::updateOrCreate(
                ['student_id' => $student->id, 'survey_question_id' => (int) $questionId],
                ['school_id' => $student->school_id, 'answer' => $answer],
            );
        }
    }

    /** Foto en almacenamiento privado: solo se sirve a usuarios autorizados. */
    private function syncPhoto(Student $student, ?UploadedFile $photo): void
    {
        if (! $photo && ! $this->remove_photo) {
            return;
        }

        $previous = $student->photo_path;
        $path = $photo?->storeAs(
            "students/{$student->school_id}",
            'photo-'.$student->id.'-'.Str::lower(Str::random(12)).'.'.Str::lower($photo->extension()),
            'local',
        );

        $student->update(['photo_path' => $path ?: null]);

        if ($previous) {
            Storage::disk('local')->delete($previous);
        }
    }
}
