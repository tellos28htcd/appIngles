<?php

namespace App\Actions\Students;

use App\Actions\Users\SendInvitation;
use App\Enums\UserStatus;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da acceso al tutor que lo solicita: crea su usuario (rol Padre/Tutor) y le
 * envía el correo para crear su contraseña. Solo verá a sus tutorados.
 * Si ya tenía usuario, solo reenvía el correo.
 */
final class GrantGuardianAccess
{
    public function __construct(private SendInvitation $sendInvitation) {}

    public function handle(Guardian $guardian): bool
    {
        $user = DB::transaction(function () use ($guardian): User {
            $user = $guardian->user ?? new User;
            $user->fill([
                'role_id' => $user->role_id ?? Role::where('slug', Role::GUARDIAN)->value('id'),
                'school_id' => $guardian->school_id,
                // El tutor se registra con su nombre completo en un solo campo.
                'first_name' => mb_substr($guardian->name, 0, 80),
                'last_name' => null,
                'second_last_name' => null,
                'email' => $guardian->email,
                'status' => UserStatus::Active,
            ])->save();

            if ($guardian->user_id !== $user->id) {
                $guardian->forceFill(['user_id' => $user->id])->save();
            }

            return $user;
        });

        return $this->sendInvitation->handle($user);
    }
}
