<?php

namespace App\Actions\Students;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentStatusChange;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cambia el estatus del alumno y lo deja en su histórico con el motivo
 * (p. ej. baja: "devolvió el material"). También activa o desactiva su acceso.
 */
final class ChangeStudentStatus
{
    public function __construct(private SyncStudentUser $syncUser) {}

    public function handle(Student $student, StudentStatus $status, ?string $reason = null, ?CarbonInterface $changedOn = null): StudentStatusChange
    {
        return DB::transaction(function () use ($student, $status, $reason, $changedOn): StudentStatusChange {
            $change = StudentStatusChange::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'from_status' => $student->exists && $student->wasRecentlyCreated === false ? $student->status : null,
                'to_status' => $status,
                'changed_on' => ($changedOn ?? now(config('appingles.timezone')))->toDateString(),
                'reason' => $reason === null || trim($reason) === '' ? null : trim($reason),
                'user_id' => auth()->id(),
            ]);

            if ($student->status !== $status) {
                $student->update(['status' => $status]);
            }

            $this->syncUser->handle($student);

            return $change;
        });
    }
}
