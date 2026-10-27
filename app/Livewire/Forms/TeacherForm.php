<?php

namespace App\Livewire\Forms;

use App\Actions\Users\SendInvitation;
use App\Enums\ContractType;
use App\Enums\TeacherStatus;
use App\Enums\UserStatus;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use App\Rules\RealImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Alta y edición de un teacher. Su usuario (rol Teacher) se crea o actualiza en
 * el mismo paso; al darlo de alta recibe el correo para crear su contraseña.
 */
class TeacherForm extends Form
{
    public ?Teacher $teacher = null;

    public ?int $school_id = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $second_last_name = '';

    public string $curp = '';

    public string $rfc = '';

    public string $email = '';

    /** @var UploadedFile|null */
    public $photo = null;

    public bool $remove_photo = false;

    public string $street = '';

    public string $exterior_number = '';

    public string $interior_number = '';

    public string $neighborhood = '';

    public string $postal_code = '';

    public ?int $state_id = null;

    public ?int $municipality_id = null;

    public string $contract_type = 'full_time';

    public ?int $weekly_hours = 48;

    public string $status = 'active';

    /** Fecha de ingreso: aparece en el panel de disponibilidad desde ese día. */
    public string $hired_on = '';

    /** Fecha de baja (solo con estatus Baja). */
    public string $terminated_on = '';

    /** Nombre corto para el panel de disponibilidad (p. ej. MELISSA). */
    public string $short_name = '';

    /** Número de columna en el panel; sin número no aparece en el panel. */
    public ?int $panel_number = null;

    public bool $is_waitlist = false;

    /** Resultado del envío de la invitación en el último alta (null si no hubo alta). */
    public ?bool $invitationSent = null;

    public function setTeacher(Teacher $teacher): void
    {
        $this->teacher = $teacher;

        $this->fill([
            'school_id' => $teacher->school_id,
            ...array_map(fn ($value) => (string) $value, $teacher->only([
                'first_name', 'last_name', 'second_last_name', 'curp', 'rfc',
                'street', 'exterior_number', 'interior_number', 'neighborhood', 'postal_code',
            ])),
            'email' => (string) $teacher->user?->email,
            'state_id' => $teacher->state_id,
            'municipality_id' => $teacher->municipality_id,
            'contract_type' => $teacher->contract_type?->value ?? ContractType::FullTime->value,
            'weekly_hours' => $teacher->weekly_hours ?? ContractType::FullTime->maxWeeklyHours(),
            'status' => $teacher->status->value,
            'hired_on' => (string) $teacher->hired_on?->toDateString(),
            'terminated_on' => (string) $teacher->terminated_on?->toDateString(),
            'short_name' => (string) $teacher->short_name,
            'panel_number' => $teacher->panel_number,
            'is_waitlist' => (bool) $teacher->is_waitlist,
        ]);
    }

    /** Tiempo completo y medio tiempo tienen tope fijo; hora clase usa las horas asignadas. */
    public function syncWeeklyHours(): void
    {
        $type = ContractType::tryFrom($this->contract_type);

        if ($type?->hasFixedHours()) {
            $this->weekly_hours = $type->maxWeeklyHours();
        }
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'second_last_name' => ['nullable', 'string', 'max:80'],
            'curp' => [
                'nullable', 'string', 'size:18', 'regex:/^[A-Z][AEIOUX][A-Z]{2}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/',
                Rule::unique('teachers', 'curp')->where('school_id', $this->school_id)->ignore($this->teacher),
            ],
            'rfc' => [
                'nullable', 'string', 'regex:/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/',
                Rule::unique('teachers', 'rfc')->where('school_id', $this->school_id)->ignore($this->teacher),
            ],
            'email' => [
                // Opcional: sin correo el teacher queda sin acceso. Si ya tiene usuario, su correo no se quita.
                Rule::requiredIf(fn () => $this->teacher?->user_id !== null),
                'nullable', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->teacher?->user_id),
            ],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048', new RealImage],
            'street' => ['nullable', 'string', 'max:150'],
            'exterior_number' => ['nullable', 'string', 'max:20'],
            'interior_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'digits:5'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'municipality_id' => [
                'nullable', 'integer',
                Rule::exists('municipalities', 'id')->where('state_id', $this->state_id),
            ],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'weekly_hours' => ['required', 'integer', 'min:1', 'max:'.ContractType::MAX_WEEKLY_HOURS],
            'status' => ['required', Rule::enum(TeacherStatus::class)],
            'hired_on' => ['required', 'date'],
            'terminated_on' => [Rule::requiredIf($this->status === TeacherStatus::Terminated->value), 'nullable', 'date'],
            'short_name' => ['nullable', 'string', 'max:20'],
            'panel_number' => ['nullable', 'integer', 'min:1', 'max:99', Rule::unique('teachers', 'panel_number')->where('school_id', $this->school_id)->ignore($this->teacher)],
            'is_waitlist' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'panel_number.unique' => __('teachers.validation.panel_number'),
            'curp.regex' => __('teachers.validation.curp'),
            'curp.size' => __('teachers.validation.curp'),
            'rfc.regex' => __('teachers.validation.rfc'),
            'email.unique' => __('users.validation.email_taken'),
            'weekly_hours.max' => __('teachers.validation.weekly_hours'),
            'municipality_id.exists' => __('schools.validation.municipality_state'),
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return collect(array_keys($this->rules()))
            ->mapWithKeys(fn (string $field) => [$field => Str::lower(__("teachers.fields.$field"))])
            ->all();
    }

    /** Guarda el teacher y su usuario. Devuelve true si fue un alta. */
    public function save(SendInvitation $sendInvitation): bool
    {
        $this->email = Str::lower(trim($this->email));
        $this->curp = Str::upper(trim($this->curp));
        $this->short_name = Str::upper(trim($this->short_name));
        $this->rfc = Str::upper(trim($this->rfc));

        $actor = auth()->user();
        if (! $actor->isPlatformAdmin()) {
            $this->school_id = $actor->school_id;
        }

        $this->syncWeeklyHours();

        $data = $this->validate();
        $photo = $data['photo'] ?? null;
        unset($data['photo']);
        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);
        // La fecha de baja solo aplica con estatus Baja.
        $data['terminated_on'] = $data['status'] === TeacherStatus::Terminated->value ? $data['terminated_on'] : null;
        $creating = $this->teacher === null;

        $userCreated = false;

        $teacher = DB::transaction(function () use ($data, &$userCreated): Teacher {
            $status = TeacherStatus::from($data['status']);
            $user = $this->teacher?->user;

            // Sin correo no hay usuario; se crea cuando se captura el correo.
            if ($data['email'] !== null) {
                $user ??= new User;
                $userCreated = ! $user->exists;

                $user->fill([
                    'role_id' => $user->role_id ?? Role::where('slug', Role::TEACHER)->value('id'),
                    'school_id' => $data['school_id'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'second_last_name' => $data['second_last_name'],
                    'email' => $data['email'],
                    // Solo un teacher activo entra al sistema.
                    'status' => $status->canWork() ? UserStatus::Active : UserStatus::Inactive,
                ])->save();
            }

            $teacher = $this->teacher ?? new Teacher;
            $teacher->fill([...collect($data)->except('email')->all(), 'user_id' => $user?->id])->save();

            return $teacher;
        });

        $this->syncPhoto($teacher, $photo);

        // Al crearse su acceso (en el alta o al capturar después su correo) recibe la invitación.
        $this->invitationSent = $userCreated ? $sendInvitation->handle($teacher->load('user')->user) : null;

        $this->setTeacher($teacher->refresh());
        $this->reset('photo', 'remove_photo');

        return $creating;
    }

    /** Foto en almacenamiento privado: solo se sirve a usuarios autorizados. */
    private function syncPhoto(Teacher $teacher, ?UploadedFile $photo): void
    {
        if (! $photo && ! $this->remove_photo) {
            return;
        }

        $previous = $teacher->photo_path;
        $path = $photo?->storeAs(
            "teachers/{$teacher->school_id}",
            'photo-'.$teacher->id.'-'.Str::lower(Str::random(12)).'.'.Str::lower($photo->extension()),
            'local',
        );

        $teacher->update(['photo_path' => $path ?: null]);

        if ($previous) {
            Storage::disk('local')->delete($previous);
        }
    }

    /** @return array<int, string> */
    public function municipalityOptions(): array
    {
        return $this->state_id
            ? Municipality::where('state_id', $this->state_id)->orderBy('name')->pluck('name', 'id')->all()
            : [];
    }
}
