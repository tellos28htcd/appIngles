<?php

namespace App\Actions\Students;

use App\Models\School;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Matrícula: PREFIJO (4) + año (2) + mes (2) + consecutivo (4), p. ej. QRON26080506.
 * El consecutivo es continuo por escuela y nunca reinicia. Un alumno que llega
 * con matrícula de otra escuela de la franquicia la conserva y no lo consume.
 */
final class EnrollmentNumbers
{
    /** Siguiente matrícula de la escuela (bloquea la fila de la escuela para no repetir). */
    public function next(School $school, ?CarbonInterface $date = null): string
    {
        $date ??= now(config('appingles.timezone'));

        return DB::transaction(function () use ($school, $date): string {
            $locked = School::query()->lockForUpdate()->findOrFail($school->id);
            $sequence = $locked->last_enrollment_sequence;

            do {
                $sequence++;
                $number = $this->format($locked, $date, $sequence);
            } while (Student::withoutGlobalScope('school')->where('enrollment_number', $number)->exists());

            $locked->forceFill(['last_enrollment_sequence' => $sequence])->save();
            $school->last_enrollment_sequence = $sequence;

            return $number;
        });
    }

    /**
     * Registra una matrícula capturada a mano o importada: si es de esta escuela
     * (su prefijo) y su consecutivo es mayor, el siguiente alumno continúa desde ahí.
     */
    public function register(School $school, string $number): void
    {
        $sequence = $this->sequenceOf($school, $number);

        if ($sequence === null) {
            return;
        }

        DB::transaction(function () use ($school, $sequence): void {
            $locked = School::query()->lockForUpdate()->findOrFail($school->id);

            if ($sequence > $locked->last_enrollment_sequence) {
                $locked->forceFill(['last_enrollment_sequence' => $sequence])->save();
                $school->last_enrollment_sequence = $sequence;
            }
        });
    }

    /** Consecutivo de una matrícula propia de la escuela; null si viene de otra escuela. */
    public function sequenceOf(School $school, string $number): ?int
    {
        $prefix = preg_quote((string) $school->enrollment_prefix, '/');

        return preg_match('/^'.$prefix.'\d{4}(\d{4,})$/', strtoupper(trim($number)), $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    public function format(School $school, CarbonInterface $date, int $sequence): string
    {
        return $school->enrollment_prefix.$date->format('ym').str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
