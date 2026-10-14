<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Tutor de uno o varios alumnos de la escuela. Su usuario (rol Padre/Tutor)
 * se crea a mano cuando lo solicita, y solo ve a sus tutorados.
 */
#[Fillable(['school_id', 'user_id', 'name', 'mobile_phone', 'email', 'receives'])]
class Guardian extends Model
{
    use Auditable, BelongsToSchool;

    /** Información que se le envía al tutor. */
    public const REPORTS = ['academic_record', 'payments', 'attendance'];

    protected function casts(): array
    {
        return [
            'receives' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Guardian $guardian): void {
            $guardian->email = $guardian->email === null || trim($guardian->email) === '' ? null : Str::lower(trim($guardian->email));
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Student, $this> */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot('relationship')->withTimestamps();
    }
}
