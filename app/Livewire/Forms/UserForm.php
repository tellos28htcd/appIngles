<?php

namespace App\Livewire\Forms;

use App\Actions\Teachers\SyncTeacherFromUser;
use App\Actions\Users\SendInvitation;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class UserForm extends Form
{
    public ?User $user = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $second_last_name = '';

    public string $email = '';

    public ?int $role_id = null;

    public ?int $school_id = null;

    public string $notes = '';

    /** Resultado del envío de la invitación en el último alta (null si no hubo alta). */
    public ?bool $invitationSent = null;

    public function setUser(User $user): void
    {
        $this->user = $user;

        $this->fill([
            'first_name' => (string) ($user->first_name ?? $user->name),
            'last_name' => (string) $user->last_name,
            'second_last_name' => (string) $user->second_last_name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'school_id' => $user->school_id,
            'notes' => (string) $user->notes,
        ]);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $actor = auth()->user();
        $role = $this->role_id ? Role::find($this->role_id) : null;
        $isPlatformRole = $role?->slug === Role::PLATFORM_ADMIN;

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'second_last_name' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user)],
            'role_id' => [
                'required', 'integer', Rule::exists('roles', 'id')->where('is_active', true),
                function (string $attribute, mixed $value, Closure $fail) use ($actor, $role): void {
                    if ($role && $actor->cannot('assignRole', [User::class, $role])) {
                        $fail(__('users.validation.role_forbidden'));
                    }

                    // Nadie cambia su propio rol (evita quedarse sin acceso o escalar privilegios).
                    if ($this->user?->is($actor) && (int) $value !== $this->user->role_id) {
                        $fail(__('users.validation.own_role'));
                    }
                },
            ],
            // RN-16: solo el Administrador plataforma va sin escuela; todos los demás, exactamente una.
            'school_id' => $isPlatformRole
                ? ['prohibited']
                : ['required', 'integer', 'exists:schools,id', function (string $attribute, mixed $value, Closure $fail) use ($actor): void {
                    if (! $actor->isPlatformAdmin() && (int) $value !== $actor->school_id) {
                        $fail(__('users.validation.school_required'));
                    }
                }],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'email.unique' => __('users.validation.email_taken'),
            'school_id.required' => __('users.validation.school_required'),
            'school_id.prohibited' => __('users.validation.school_forbidden'),
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return collect(['first_name', 'last_name', 'second_last_name', 'email', 'role_id', 'school_id', 'notes'])
            ->mapWithKeys(fn (string $field) => [$field => Str::lower(__("users.fields.$field"))])
            ->all();
    }

    /**
     * Guarda el usuario. Si es nuevo, le envía la invitación para crear su
     * contraseña (RN-25). Devuelve true si fue un alta.
     */
    public function save(SendInvitation $sendInvitation): bool
    {
        $this->email = Str::lower(trim($this->email));

        $actor = auth()->user();
        if (! $actor->isPlatformAdmin()) {
            $this->school_id = $actor->school_id;
        }

        $data = $this->validate();
        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);
        $creating = $this->user === null;

        $user = DB::transaction(function () use ($data): User {
            $user = $this->user ?? new User(['status' => UserStatus::Active]);
            $user->fill([...$data, 'school_id' => $data['school_id'] ?? null])->save();

            // Un usuario Teacher siempre tiene su ficha en el módulo Teachers.
            app(SyncTeacherFromUser::class)->handle($user);

            return $user;
        });

        if ($creating) {
            $this->invitationSent = $sendInvitation->handle($user);
        }

        $this->setUser($user->refresh());

        return $creating;
    }
}
