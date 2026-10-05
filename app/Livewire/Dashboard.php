<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(): View
    {
        $user = auth()->user()->loadMissing('role');
        $today = now(config('appingles.timezone'));

        return view('livewire.dashboard', [
            'user' => $user,
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
