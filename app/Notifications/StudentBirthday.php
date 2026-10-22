<?php

namespace App\Notifications;

use App\Models\Student;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Felicitación de cumpleaños al alumno: ventana al entrar al sistema (hasta que
 * la marque como leída o la cierre, y hasta 7 días después) y, solo si se activa
 * BIRTHDAY_MAIL=true en producción, también por correo.
 */
class StudentBirthday extends Notification
{
    /** Días después del cumpleaños en que la ventana aún aparece si no la ha visto. */
    public const VISIBLE_DAYS = 7;

    public function __construct(private readonly Student $student, private readonly string $birthdayOn) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return config('appingles.birthdays.mail') ? ['database', 'mail'] : ['database'];
    }

    /** @return array{student_id: int, school_id: int, first_name: string, school: string, birthday_on: string, age: ?int} */
    public function toArray(User $notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'school_id' => $this->student->school_id,
            'first_name' => $this->student->first_name,
            'school' => (string) $this->student->school?->name,
            'birthday_on' => $this->birthdayOn,
            'age' => $this->student->birth_date ? (int) $this->student->birth_date->diffInYears($this->birthdayOn) : null,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $school = $this->student->school?->name ?? config('appingles.brand.name');

        return (new MailMessage)
            ->subject(__('birthdays.mail.subject', ['name' => $this->student->first_name]))
            ->greeting(__('birthdays.mail.greeting', ['name' => $this->student->first_name]))
            ->line(__('birthdays.mail.body', ['school' => $school]))
            ->salutation(__('birthdays.mail.salutation', ['school' => $school]));
    }
}
