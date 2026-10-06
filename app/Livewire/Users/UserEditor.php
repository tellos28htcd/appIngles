<?php

namespace App\Livewire\Users;

use App\Actions\Users\SendInvitation;
use App\Livewire\Forms\UserForm;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Alta y edición de usuarios. */
#[Layout('layouts.app')]
class UserEditor extends Component
{
    public UserForm $form;

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->authorize('update', $user);
            $this->form->setUser($user);
        } else {
            $this->authorize('create', User::class);
            $this->form->school_id = auth()->user()->isPlatformAdmin()
                ? (int) request()->query('escuela') ?: null
                : auth()->user()->school_id;
        }
    }

    public function updatedFormRoleId(): void
    {
        if ($this->selectedRoleIsPlatform()) {
            $this->form->school_id = null;
        }
    }

    public function save(SendInvitation $sendInvitation): void
    {
        $this->form->user
            ? $this->authorize('update', $this->form->user)
            : $this->authorize('create', User::class);

        $created = $this->form->save($sendInvitation);

        $mailFailed = $created && $this->form->invitationSent === false;

        session()->flash('toast', [
            'type' => $mailFailed ? 'warning' : 'success',
            'message' => __(match (true) {
                $mailFailed => 'users.messages.created_mail_failed',
                $created => 'users.messages.created',
                default => 'users.messages.updated',
            }),
        ]);

        $this->redirectRoute('users.index', navigate: true);
    }

    /** Roles que el usuario conectado puede asignar. @return array<int, string> */
    #[Computed]
    public function roles(): array
    {
        $actor = auth()->user();

        return Role::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Role $role) => $actor->can('assignRole', [User::class, $role]))
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function schools(): array
    {
        return School::query()->orderBy('name')->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (School $school) => [$school->id => "{$school->code} · {$school->name}"])
            ->all();
    }

    public function selectedRoleIsPlatform(): bool
    {
        return $this->form->role_id !== null
            && Role::whereKey($this->form->role_id)->value('slug') === Role::PLATFORM_ADMIN;
    }

    public function render(): View
    {
        $editing = $this->form->user !== null;

        return view('livewire.users.user-editor', [
            'editing' => $editing,
            'isSelf' => $editing && $this->form->user->is(auth()->user()),
            'actorIsPlatform' => auth()->user()->isPlatformAdmin(),
            'platformRole' => $this->selectedRoleIsPlatform(),
        ])->title(__($editing ? 'users.edit_title' : 'users.create_title'));
    }
}
