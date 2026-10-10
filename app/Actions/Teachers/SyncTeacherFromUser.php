<?php

namespace App\Actions\Teachers;

use App\Enums\TeacherStatus;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;

/**
 * Un usuario con rol Teacher creado o editado desde Usuarios tiene su ficha en
 * Teachers. Si no existía, se crea "con datos incompletos" (sin contratación).
 */
final class SyncTeacherFromUser
{
    public function handle(User $user): ?Teacher
    {
        $user->loadMissing('role');

        if ($user->role?->slug !== Role::TEACHER || $user->school_id === null) {
            return null;
        }

        $teacher = Teacher::withoutGlobalScope('school')->firstOrNew(['user_id' => $user->id]);
        $names = $user->first_name
            ? ['first_name' => $user->first_name, 'last_name' => $user->last_name ?: '—', 'second_last_name' => $user->second_last_name]
            : Teacher::splitName($user->name);

        $teacher->fill([...$names, 'school_id' => $user->school_id]);

        if (! $teacher->exists) {
            $teacher->status = $user->isActive() ? TeacherStatus::Active : TeacherStatus::Leave;
        }

        $teacher->save();

        return $teacher;
    }
}
