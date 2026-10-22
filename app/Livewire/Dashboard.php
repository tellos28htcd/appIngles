<?php

namespace App\Livewire;

use App\Enums\StudentStatus;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    /** @var list<string> Roles que ven los cumpleaños del día. */
    private const BIRTHDAY_ROLES = [Role::SCHOOL_ADMIN, Role::RECEPTION];

    public function render(): View
    {
        $user = auth()->user()->loadMissing('role');
        $today = now(config('appingles.timezone'));

        // Cumpleaños de hoy: lo ven Recepción y la administración de la escuela, para felicitar al alumno.
        $birthdays = $user->school_id !== null && in_array($user->role?->slug, self::BIRTHDAY_ROLES, true)
            ? Student::query()->where('school_id', $user->school_id)
                ->whereIn('status', collect(StudentStatus::cases())->filter->canLogIn()->all())
                ->birthdayOn($today)->orderBy('first_name')->get()
            : null;

        return view('livewire.dashboard', [
            'user' => $user,
            'birthdays' => $birthdays,
            'firstName' => Str::before($user->name, ' '),
            'todayLabel' => Str::ucfirst($today->translatedFormat('l j \d\e F \d\e Y')),
            'roadmap' => [
                'access' => 'done',
                'schools' => 'next',
                'users' => 'pending',
                'catalogs' => 'pending',
                'students' => 'pending',
            ],
        ])->title(__('dashboard.title'));
    }
}
