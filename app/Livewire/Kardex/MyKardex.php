<?php

namespace App\Livewire\Kardex;

use App\Actions\Kardex\KardexService;
use App\Models\KardexEntry;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Mi aprendizaje → Mi avance: el Kardex del alumno (práctica que sigue, puntos y calificaciones), solo lectura. */
#[Layout('layouts.app')]
class MyKardex extends Component
{
    public function mount(): void
    {
        $this->authorize('viewOwn', KardexEntry::class);
    }

    public function render(KardexService $kardex): View
    {
        $student = Student::query()->where('user_id', auth()->id())->first();

        return view('livewire.kardex.my-kardex', [
            'student' => $student,
            'summary' => $student ? $kardex->summary($student) : null,
        ])->title(__('kardex.my_title'));
    }
}
