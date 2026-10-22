<?php

namespace App\Livewire;

use App\Notifications\StudentBirthday;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Ventana de felicitación de cumpleaños del alumno. Aparece al entrar al sistema
 * mientras no la marque como leída o la cierre, hasta 7 días después de su
 * cumpleaños. Va en el layout de la aplicación.
 */
class BirthdayGreeting extends Component
{
    #[Computed]
    public function greeting(): ?DatabaseNotification
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $since = now(config('appingles.timezone'))->subDays(StudentBirthday::VISIBLE_DAYS)->toDateString();

        return $user->unreadNotifications()->where('type', StudentBirthday::class)->latest()->get()
            ->first(fn (DatabaseNotification $notification) => ($notification->data['birthday_on'] ?? '') >= $since);
    }

    /** "Marcar como leída" y "Cerrar" la quitan: ya no vuelve a aparecer. */
    public function dismiss(): void
    {
        $this->greeting?->markAsRead();
        unset($this->greeting);
    }

    public function render(): View
    {
        return view('livewire.birthday-greeting');
    }
}
