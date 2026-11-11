<?php

namespace App\Livewire;

use App\Notifications\BookingMoved;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Avisos al alumno de cambios de aula por el balanceo de la agenda: ventana al entrar al sistema
 * mientras tenga avisos sin leer de reservaciones de hoy en adelante. Va en el layout de la aplicación.
 */
class BookingNotices extends Component
{
    /** @return Collection<int, DatabaseNotification> */
    #[Computed]
    public function notices(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        $today = now(config('appingles.timezone'))->toDateString();

        return $user->unreadNotifications()->where('type', BookingMoved::class)->oldest()->get()
            ->filter(fn (DatabaseNotification $notice) => ($notice->data['date'] ?? '') >= $today)
            // Si la misma reservación se movió varias veces, solo el último lugar.
            ->reverse()->unique(fn (DatabaseNotification $notice) => $notice->data['booking_id'] ?? $notice->id)->reverse()->values();
    }

    /** "Entendido": marca como leídos todos los avisos de cambio de aula. */
    public function dismiss(): void
    {
        auth()->user()?->unreadNotifications()->where('type', BookingMoved::class)->update(['read_at' => now()]);
        unset($this->notices);
    }

    public function render(): View
    {
        return view('livewire.booking-notices');
    }
}
