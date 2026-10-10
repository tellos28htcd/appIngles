<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['role_id', 'school_id', 'first_name', 'last_name', 'second_last_name', 'name', 'email', 'notes', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    /**
     * Relaciones que, si tienen registros, impiden eliminar al usuario (RN-24).
     * Cada módulo nuevo que guarde al usuario como autor o responsable
     * agrega aquí su relación (p. ej. 'classSessions', 'payments').
     *
     * @var list<string>
     */
    public const RECORD_RELATIONS = [];

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $fullName = trim(implode(' ', array_filter([$user->first_name, $user->last_name, $user->second_last_name])));

            if ($fullName !== '') {
                $user->name = $fullName;
            }

            $user->email = Str::lower(trim((string) $user->email));
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Usuarios que el actor puede ver: el Super Admin ve todos; los demás,
     * solo los de su escuela y nunca a los administradores de plataforma.
     *
     * @param  Builder<User>  $query
     */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if ($actor->isPlatformAdmin()) {
            return;
        }

        $query->where('school_id', $actor->school_id)
            ->whereNotNull('school_id')
            ->whereHas('role', fn (Builder $role) => $role->where('slug', '!=', Role::PLATFORM_ADMIN));
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->loadMissing('role')->role?->slug === Role::PLATFORM_ADMIN;
    }

    public function isSchoolAdmin(): bool
    {
        return $this->loadMissing('role')->role?->slug === Role::SCHOOL_ADMIN;
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    /** ¿Tiene registros en algún módulo? Entonces solo se puede desactivar. */
    public function hasModuleRecords(): bool
    {
        foreach (self::RECORD_RELATIONS as $relation) {
            if ($this->{$relation}()->exists()) {
                return true;
            }
        }

        return false;
    }

    /** Cada inicio de sesión ya queda en login_logs; no se repite aquí. */
    protected function auditExcept(): array
    {
        return ['remember_token', 'last_login_at', 'email_verified_at'];
    }

    /** De la contraseña solo se registra que cambió, nunca su valor. */
    protected function auditRedact(): array
    {
        return ['password'];
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }
}
