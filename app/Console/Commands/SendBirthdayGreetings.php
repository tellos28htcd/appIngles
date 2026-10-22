<?php

namespace App\Console\Commands;

use App\Enums\StudentStatus;
use App\Models\School;
use App\Models\Student;
use App\Notifications\StudentBirthday;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Felicita a los alumnos que cumplen años hoy (en la zona horaria de su escuela).
 * Solo a quien puede entrar al sistema (con usuario, Activo o En pausa). Se puede
 * correr varias veces: no repite la felicitación del mismo año. Lo corre el programador
 * cada mañana (routes/console.php). Uso: php artisan students:birthday-greetings [--date=AAAA-MM-DD]
 */
class SendBirthdayGreetings extends Command
{
    protected $signature = 'students:birthday-greetings {--date= : Fecha a revisar (AAAA-MM-DD); por omisión, hoy}';

    protected $description = 'Envía la felicitación de cumpleaños a los alumnos que cumplen años hoy';

    public function handle(): int
    {
        $sent = 0;

        foreach (School::query()->orderBy('id')->get() as $school) {
            $today = $this->option('date')
                ? CarbonImmutable::parse($this->option('date'))
                : CarbonImmutable::now($school->timezone ?: config('appingles.timezone'))->startOfDay();

            $students = Student::withoutGlobalScope('school')->where('school_id', $school->id)
                ->whereNotNull('user_id')
                ->whereIn('status', collect(StudentStatus::cases())->filter->canLogIn()->all())
                ->birthdayOn($today)
                ->with(['user', 'school'])
                ->get();

            foreach ($students as $student) {
                $already = DatabaseNotification::query()
                    ->where('notifiable_type', $student->user->getMorphClass())->where('notifiable_id', $student->user_id)
                    ->where('type', StudentBirthday::class)
                    ->whereYear('created_at', $today->year)
                    ->exists();

                if (! $already) {
                    $student->user->notify(new StudentBirthday($student, $today->toDateString()));
                    $sent++;
                }
            }
        }

        $this->info(trans_choice('birthdays.command_done', $sent));

        return self::SUCCESS;
    }
}
