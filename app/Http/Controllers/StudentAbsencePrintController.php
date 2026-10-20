<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentAbsence;
use App\Support\Brand;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Documento imprimible "Solicitud de ausencia": periodo, días autorizados,
 * acumulado del ciclo, días restantes y fin del curso, con firmas.
 * (Formato provisional hasta recibir el de la escuela.)
 */
class StudentAbsencePrintController extends Controller
{
    public function __invoke(Student $student, StudentAbsence $absence): View
    {
        Gate::authorize('view', $student);
        abort_unless($absence->student_id === $student->id, 404);

        $student->load(['enrollments', 'absences', 'school']);
        $used = $student->absenceDaysInCycle();

        return view('students.absence-print', [
            'student' => $student,
            'absence' => $absence,
            'used' => $used,
            'left' => max(0, Student::MAX_ABSENCE_DAYS - $used),
            'brand' => Brand::current(),
        ]);
    }
}
