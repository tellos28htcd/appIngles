<?php

namespace App\Models;

use App\Enums\LoginEvent;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Bitácora de accesos. Solo se inserta; se conserva para siempre (RN-12).
 * Lleva la escuela del usuario para que su administrador vea los accesos
 * de su gente; los intentos con un correo desconocido quedan sin escuela.
 */
#[Fillable(['user_id', 'school_id', 'email', 'event', 'ip_address', 'user_agent'])]
class LoginLog extends Model
{
    use BelongsToSchool;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event' => LoginEvent::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(LoginEvent $event, string $email, ?User $user, Request $request): self
    {
        return self::create([
            'user_id' => $user?->id,
            'school_id' => $user?->school_id,
            'email' => mb_strtolower($email),
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
