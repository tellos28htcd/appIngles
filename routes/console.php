<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Felicitación de cumpleaños a los alumnos (requiere el cron de schedule:run en el servidor).
Schedule::command('students:birthday-greetings')->dailyAt(config('appingles.birthdays.time'))
    ->timezone(config('appingles.timezone'))->withoutOverlapping();
