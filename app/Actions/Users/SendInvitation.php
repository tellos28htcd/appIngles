<?php

namespace App\Actions\Users;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

final class SendInvitation
{
    /**
     * Envía el correo para crear la contraseña. Devuelve false si el servidor
     * de correo falló (el usuario ya existe y se puede reenviar después).
     */
    public function handle(User $user): bool
    {
        $token = Password::broker('invitations')->createToken($user);

        try {
            $user->notify(new UserInvitation($token));
            AuditLog::record($user, 'invitation_sent', null, ['email' => $user->email]);

            return true;
        } catch (Throwable $exception) {
            Log::error('No se pudo enviar la invitación de usuario.', [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
