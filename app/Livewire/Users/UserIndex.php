<?php

namespace App\Livewire\Users;

use App\Actions\Users\SendInvitation;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de usuarios. El Super Admin ve todas las escuelas y filtra por
 * escuela; el administrador de escuela solo ve la suya.
 */
#[Layout('layouts.app')]
class UserIndex extends Component
{
    use WithPagination;

    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'escuela', except: '')]
    public string $school = '';

    #[Url(as: 'rol', except: '')]
    public string $role = '';

    #[Url(as: 'estado', except: '')]
    public string $status = '';

    #[Url(as: 'por_pagina', except: 25)]
    public int $perPage = 25;

    public ?int $confirmingDeletionId = null;

    public ?int $confirmingDeactivationId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 25;
        }

        if (in_array($property, ['search', 'school', 'role', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'school', 'role', 'status');
        $this->resetPage();
    }

    public function confirmDeletion(int $userId): void
    {
        $this->authorize('delete', $this->findVisible($userId));
        $this->confirmingDeletionId = $userId;
    }

    public function delete(): void
    {
        $user = $this->findVisible($this->confirmingDeletionId);

        if ($user->hasModuleRecords()) {
            $this->confirmingDeletionId = null;
            $this->dispatch('toast', type: 'error', message: __('users.messages.cannot_delete'));

            return;
        }

        $this->authorize('delete', $user);
        $user->delete();
        $this->confirmingDeletionId = null;

        $this->dispatch('toast', type: 'success', message: __('users.messages.deleted'));
    }

    public function confirmDeactivation(int $userId): void
    {
        $this->authorize('toggleStatus', $this->findVisible($userId));
        $this->confirmingDeactivationId = $userId;
    }

    public function deactivate(): void
    {
        $user = $this->findVisible($this->confirmingDeactivationId);
        $this->authorize('toggleStatus', $user);

        $user->update(['status' => UserStatus::Inactive]);
        $this->confirmingDeactivationId = null;

        $this->dispatch('toast', type: 'success', message: __('users.messages.deactivated'));
    }

    public function activate(int $userId): void
    {
        $user = $this->findVisible($userId);
        $this->authorize('toggleStatus', $user);

        $user->update(['status' => UserStatus::Active]);

        $this->dispatch('toast', type: 'success', message: __('users.messages.activated'));
    }

    public function resendInvitation(int $userId, SendInvitation $sendInvitation): void
    {
        $user = $this->findVisible($userId);
        $this->authorize('resendInvitation', $user);

        if (! $sendInvitation->handle($user)) {
            $this->dispatch('toast', type: 'error', message: __('users.messages.mail_failed'));

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('users.messages.invitation_sent', ['email' => $user->email]));
    }

    /** Solo usuarios que el actor puede ver; un ID ajeno responde 404. */
    private function findVisible(?int $userId): User
    {
        return User::query()->visibleTo(auth()->user())->with(['role', 'school'])->findOrFail($userId);
    }

    #[Computed]
    public function pendingUser(): ?User
    {
        $id = $this->confirmingDeletionId ?? $this->confirmingDeactivationId;

        return $id ? User::query()->visibleTo(auth()->user())->find($id) : null;
    }

    /** @return LengthAwarePaginator<int, User> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $actor = auth()->user();
        $term = trim($this->search);

        return User::query()
            ->visibleTo($actor)
            ->with(['role:id,slug,name', 'school:id,code,name'])
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($actor->isPlatformAdmin() && $this->school !== '', fn (Builder $query) => $this->school === 'plataforma'
                ? $query->whereNull('school_id')
                : $query->where('school_id', (int) $this->school))
            ->when($this->role !== '', fn (Builder $query) => $query->where('role_id', (int) $this->role))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->orderByRaw('school_id is not null')
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    /** @return array<string, string> */
    #[Computed]
    public function schoolOptions(): array
    {
        return ['plataforma' => __('users.no_school')] + School::query()->orderBy('name')->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (School $school) => [(string) $school->id => "{$school->code} · {$school->name}"])
            ->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function roleOptions(): array
    {
        $actor = auth()->user();

        return Role::query()
            ->orderBy('sort_order')
            ->when(! $actor->isPlatformAdmin(), fn (Builder $query) => $query->where('slug', '!=', Role::PLATFORM_ADMIN))
            ->pluck('name', 'id')
            ->all();
    }

    public function render(): View
    {
        $actor = auth()->user();

        return view('livewire.users.user-index', [
            'actor' => $actor,
            'isPlatform' => $actor->isPlatformAdmin(),
            'hasFilters' => $this->search !== '' || $this->school !== '' || $this->role !== '' || $this->status !== '',
            'statusOptions' => collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $s) => [$s->value => $s->label()])->all(),
        ])->title(__('users.title'));
    }
}
