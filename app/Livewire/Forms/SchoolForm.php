<?php

namespace App\Livewire\Forms;

use App\Actions\Catalogs\CopyBaseCatalogs;
use App\Enums\CourseEndFrom;
use App\Enums\FailedActivityPolicy;
use App\Enums\MaxSessionsScope;
use App\Enums\SelfBooking;
use App\Models\Book;
use App\Models\Municipality;
use App\Models\School;
use App\Rules\RealImage;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class SchoolForm extends Form
{
    public ?School $school = null;

    public string $code = '';

    public string $enrollment_prefix = '';

    public string $name = '';

    public string $street = '';

    public string $exterior_number = '';

    public string $interior_number = '';

    public string $neighborhood = '';

    public string $postal_code = '';

    public ?int $state_id = null;

    public ?int $municipality_id = null;

    public string $phone = '';

    public string $rfc = '';

    public string $legal_name = '';

    public int $last_folio_series_a = 0;

    public int $last_folio_series_b = 0;

    public string $brand_primary = '#4361EE';

    public string $brand_accent = '#FFC23D';

    /** @var UploadedFile|null */
    public $logo = null;

    public bool $remove_logo = false;

    public ?int $session_capacity = null;

    /** Cupo de alumnos por club presencial (vacío = el cupo del aula). */
    public ?int $club_capacity = null;

    /** Alumnos por aula virtual (asesorías y clubs en línea). */
    public ?int $virtual_room_capacity = null;

    /** Aulas virtuales disponibles por horario para asesoría en línea (0 = sin asesoría en línea). */
    public int $virtual_rooms_per_slot = 0;

    public string $timezone = 'America/Mexico_City';

    public string $currency = 'MXN';

    public bool $works_sundays = false;

    public bool $schedules_classrooms = true;

    public bool $books_without_classroom = false;

    public bool $hybrid_clubs = false;

    public bool $requires_progress = true;

    public string $self_booking = 'disabled';

    /** Fecha de la ficha desde la que se cuenta el fin del curso: contract | material. */
    public string $course_end_from = 'contract';

    public string $max_sessions_scope = 'student';

    public ?int $max_sessions = null;

    /** Cantidad máxima de clubs por alumno (vacío = sin límite). */
    public ?int $max_clubs = null;

    public string $failed_activity_policy = 'shift';

    public ?int $club_min_lesson_number = null;

    public function setSchool(School $school): void
    {
        $this->school = $school;

        $this->fill([
            ...$school->only([
                'code', 'enrollment_prefix', 'name', 'state_id', 'municipality_id', 'last_folio_series_a', 'last_folio_series_b',
                'brand_primary', 'session_capacity', 'club_capacity', 'virtual_room_capacity', 'virtual_rooms_per_slot', 'timezone', 'currency', 'max_sessions', 'max_clubs', 'club_min_lesson_number',
                'works_sundays', 'schedules_classrooms', 'books_without_classroom', 'hybrid_clubs', 'requires_progress',
            ]),
            ...array_map(fn ($value) => (string) $value, $school->only([
                'street', 'exterior_number', 'interior_number', 'neighborhood', 'postal_code', 'phone', 'rfc', 'legal_name',
            ])),
            'brand_accent' => $school->brand_accent ?? config('appingles.brand.accent'),
            'self_booking' => $school->self_booking->value,
            'course_end_from' => $school->course_end_from?->value ?? CourseEndFrom::Contract->value,
            'max_sessions_scope' => $school->max_sessions_scope->value,
            'failed_activity_policy' => $school->failed_activity_policy->value,
        ]);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique('schools', 'code')->ignore($this->school)],
            'enrollment_prefix' => ['nullable', 'string', 'size:4', 'regex:/^[A-Z]{4}$/'],
            'name' => ['required', 'string', 'max:150'],
            'street' => ['nullable', 'string', 'max:150'],
            'exterior_number' => ['nullable', 'string', 'max:20'],
            'interior_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'digits:5'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'municipality_id' => [
                'required', 'integer',
                Rule::exists('municipalities', 'id')->where('state_id', $this->state_id),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +()\-]{7,20}$/'],
            'rfc' => ['nullable', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'last_folio_series_a' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'last_folio_series_b' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'brand_primary' => $hex,
            'brand_accent' => $hex,
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024', new RealImage],
            'session_capacity' => ['required', 'integer', 'min:1', 'max:'.School::MAX_SESSION_CAPACITY],
            'club_capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'virtual_room_capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'virtual_rooms_per_slot' => ['required', 'integer', 'min:0', 'max:20'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'MX'))],
            'currency' => ['required', Rule::in(['MXN', 'USD'])],
            'works_sundays' => ['boolean'],
            'schedules_classrooms' => ['boolean'],
            'books_without_classroom' => ['boolean'],
            'hybrid_clubs' => ['boolean'],
            'requires_progress' => ['boolean'],
            'self_booking' => ['required', Rule::enum(SelfBooking::class)],
            'course_end_from' => ['required', Rule::enum(CourseEndFrom::class)],
            'max_sessions_scope' => ['required', Rule::enum(MaxSessionsScope::class)],
            'max_sessions' => ['nullable', 'integer', 'min:1', 'max:99'],
            'max_clubs' => ['nullable', 'integer', 'min:1', 'max:99'],
            'failed_activity_policy' => ['required', Rule::enum(FailedActivityPolicy::class)],
            'club_min_lesson_number' => ['nullable', 'integer', Rule::in(array_keys($this->lessonOptions()))],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'municipality_id.exists' => __('schools.validation.municipality_state'),
            'rfc.regex' => __('schools.validation.rfc'),
            'brand_primary.regex' => __('schools.validation.color'),
            'brand_accent.regex' => __('schools.validation.color'),
            'postal_code.digits' => __('schools.validation.postal_code'),
            'enrollment_prefix.regex' => __('schools.validation.enrollment_prefix'),
            'enrollment_prefix.size' => __('schools.validation.enrollment_prefix'),
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return collect(array_keys($this->rules()))
            ->mapWithKeys(fn (string $field) => [$field => Str::lower(__("schools.fields.$field"))])
            ->all();
    }

    public function save(): School
    {
        $this->rfc = Str::upper(trim($this->rfc));
        $this->code = Str::upper(trim($this->code));
        $this->enrollment_prefix = Str::upper(trim($this->enrollment_prefix));
        $this->brand_primary = Str::upper($this->brand_primary);
        $this->brand_accent = Str::upper($this->brand_accent);

        $data = $this->validate();
        $logo = $data['logo'] ?? null;
        unset($data['logo']);

        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);

        $creating = $this->school === null;

        $school = DB::transaction(function () use ($data, $creating): School {
            $school = $this->school ?? new School;
            $school->fill($data)->save();

            // Toda escuela nueva parte del catálogo académico base.
            if ($creating) {
                app(CopyBaseCatalogs::class)->handle($school);
            }

            return $school;
        });

        $this->syncLogo($school, $logo);
        $this->setSchool($school->refresh());
        $this->reset('logo', 'remove_logo');

        return $school;
    }

    private function syncLogo(School $school, ?UploadedFile $logo): void
    {
        if (! $logo && ! $this->remove_logo) {
            return;
        }

        $previous = $school->logo_path;
        $path = $logo?->storeAs(
            "schools/{$school->id}",
            'logo-'.Str::lower(Str::random(12)).'.'.Str::lower($logo->extension()),
            'public',
        );

        $school->update(['logo_path' => $path ?: null]);

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }
    }

    /** @return array<int, string> */
    public function municipalityOptions(): array
    {
        if (! $this->state_id) {
            return [];
        }

        return Municipality::query()
            ->where('state_id', $this->state_id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Lecciones para "¿a partir de cuál lección participa en clubes?": las del
     * primer libro de la escuela (o del catálogo base si aún no tiene).
     *
     * @return array<int, string> número de actividad => nombre
     */
    public function lessonOptions(): array
    {
        $book = Book::withoutGlobalScope('school')
            ->ofCatalog($this->school?->books()->exists() ? $this->school->id : null)
            ->orderBy('level')
            ->first();

        return $book?->lessons()->withoutGlobalScope('school')->pluck('name', 'number')->all() ?? [];
    }
}
