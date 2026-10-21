<?php

namespace App\Livewire\Teachers;

use App\Models\Teacher;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Página "Horario base" del teacher: qué horas del catálogo trabaja cada día.
 * La cuadrícula es BaseScheduleEditor (también se abre desde el panel de
 * disponibilidad); con ella se arman las semanas del panel.
 */
#[Layout('layouts.app')]
class TeacherSchedule extends Component
{
    #[Locked]
    public int $teacherId;

    public function mount(Teacher $teacher): void
    {
        $this->authorize('editSchedule', $teacher);
        $this->teacherId = $teacher->id;
    }

    public function render(): View
    {
        $teacher = Teacher::query()->where('school_id', auth()->user()->school_id)->findOrFail($this->teacherId);

        return view('livewire.teachers.teacher-schedule', ['teacher' => $teacher])
            ->title(__('availability.schedule_title', ['teacher' => $teacher->fullName()]));
    }
}
