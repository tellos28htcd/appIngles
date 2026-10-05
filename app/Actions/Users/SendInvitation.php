<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Password;

final class SendInvitation
{
    public function handle(User $user): void
    {
        $token = Password::broker('invitations')->createToken($user);

        $user->notify(new UserInvitation($token));
    }
}
