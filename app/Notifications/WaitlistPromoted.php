<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al alumno: estaba en lista de espera, se liberó un lugar y ya tiene aula (pasó solo). Por correo y, si tiene
 * cuenta, en el sistema (ventana al entrar, junto con los cambios de aula).
 */
class WaitlistPromoted extends Notification
{
    public function __construct(private readonly Booking $booking, private readonly ScheduleSlot $slot, private readonly string $teacher) {}

    /** @return list<string> */
    public function via(User|AnonymousNotifiable $notifiable): array
    {
        return $notifiable instanceof User ? ['database', 'mail'] : ['mail'];
    }

    /** @return array{booking_id: int, date: string, hour: string, place: string, teacher: string} */
    public function toArray(User $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'date' => $this->booking->date->toDateString(),
            'hour' => $this->slot->range(),
            'place' => $this->booking->placeLabel(),
            'teacher' => $this->teacher,
        ];
    }

    public function toMail(User|AnonymousNotifiable $notifiable): MailMessage
    {
        $school = School::withoutGlobalScopes()->find($this->booking->school_id)?->name ?? config('appingles.brand.name');
        $name = (string) $this->booking->student?->first_name;

        return (new MailMessage)
            ->subject(__('agenda.promoted.mail_subject'))
            ->greeting(__('agenda.promoted.mail_greeting', ['name' => $name]))
            ->line(__('agenda.promoted.mail_body'))
            ->line(__('agenda.promoted.mail_when', [
                'date' => CarbonImmutable::parse($this->booking->date->toDateString())->translatedFormat('l j \d\e F'),
                'hour' => $this->slot->range(),
            ]))
            ->line(__('agenda.promoted.mail_where', ['place' => $this->booking->placeLabel()]).($this->teacher !== '' ? ' · '.__('agenda.moved.with', ['teacher' => $this->teacher]) : ''))
            ->salutation(__('agenda.promoted.mail_salutation', ['school' => $school]));
    }
}
