<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Correo de bienvenida con el enlace para crear la contraseña (RN-25). */
class UserInvitation extends Notification
{
    public function __construct(private readonly string $token) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $place = $notifiable->school?->name ?? config('appingles.brand.name');
        $hours = (int) config('auth.passwords.invitations.expire') / 60;

        return (new MailMessage)
            ->subject(__('invitations.mail.subject', ['place' => $place]))
            ->greeting(__('invitations.mail.greeting', ['name' => $notifiable->first_name ?: $notifiable->name]))
            ->line(__('invitations.mail.intro', ['place' => $place, 'role' => $notifiable->role?->name]))
            ->action(__('invitations.mail.action'), route('invitation.accept', [
                'token' => $this->token,
                'email' => $notifiable->email,
            ]))
            ->line(__('invitations.mail.expires', ['hours' => $hours]))
            ->line(__('invitations.mail.ignore'))
            ->salutation(__('invitations.mail.salutation', ['brand' => config('appingles.brand.name')]));
    }
}
