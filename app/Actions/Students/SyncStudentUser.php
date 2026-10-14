<?php

namespace App\Actions\Students;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;

/**
 * Usuario del alumno (rol Alumno). Sin correo no hay usuario; al capturarlo
 * se crea. Nunca envía correo: el acceso se manda con "Enviar acceso".
 * Solo un alumno activo o en pausa puede entrar.
 */
final class SyncStudentUser
{
    public function handle(Student $student): ?User
    {
        $user = $student->user;

        if ($student->email === null && $user === null) {
            return null;
        }

        $user ??= new User;
        $user->fill([
            'role_id' => $user->role_id ?? Role::where('slug', Role::STUDENT)->value('id'),
            'school_id' => $student->school_id,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'second_last_name' => $student->second_last_name,
            'email' => $student->email ?? $user->email,
            'status' => $student->status->canLogIn() ? UserStatus::Active : UserStatus::Inactive,
        ]);

        if ($user->isDirty()) {
            $user->save();
        }

        if ($student->user_id !== $user->id) {
            $student->forceFill(['user_id' => $user->id])->save();
        }

        return $user;
    }
}
