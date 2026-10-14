<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Expediente del alumno. Con correo tiene usuario (rol Alumno); su acceso
 * solo se envía cuando la escuela presiona "Enviar acceso".
 */
#[Fillable([
    'school_id', 'user_id', 'enrollment_number',
    'first_name', 'last_name', 'second_last_name', 'mobile_phone', 'address', 'email', 'birth_date',
    'student_type_id', 'information_source_id', 'advertising_consent',
    'learns_visual', 'learns_auditory', 'learns_kinesthetic',
    'photo_path', 'photo_source_url', 'status', 'notes',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'advertising_consent' => 'boolean',
            'learns_visual' => 'boolean',
            'learns_auditory' => 'boolean',
            'learns_kinesthetic' => 'boolean',
            'status' => StudentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Student $student): void {
            $student->email = $student->email === null || trim($student->email) === '' ? null : Str::lower(trim($student->email));
            $student->enrollment_number = Str::upper(trim((string) $student->enrollment_number));
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<StudentType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(StudentType::class, 'student_type_id');
    }

    /** @return BelongsTo<InformationSource, $this> */
    public function informationSource(): BelongsTo
    {
        return $this->belongsTo(InformationSource::class);
    }

    /** @return HasMany<StudentEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /** @return HasOne<StudentEnrollment, $this> */
    public function latestEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)->latestOfMany();
    }

    /** @return HasMany<StudentAbsence, $this> */
    public function absences(): HasMany
    {
        return $this->hasMany(StudentAbsence::class);
    }

    /** @return HasMany<StudentStatusChange, $this> */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(StudentStatusChange::class);
    }

    /** @return BelongsToMany<Guardian, $this> */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)->withPivot('relationship')->withTimestamps();
    }

    /** @return HasMany<StudentSurveyAnswer, $this> */
    public function surveyAnswers(): HasMany
    {
        return $this->hasMany(StudentSurveyAnswer::class);
    }

    /**
     * Búsqueda por matrícula, nombre, correo o teléfono.
     *
     * @param  Builder<Student>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->where('enrollment_number', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('mobile_phone', 'like', "%{$term}%")
            ->orWhereRaw("concat_ws(' ', first_name, last_name, second_last_name) like ?", ["%{$term}%"]));
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->last_name, $this->second_last_name])));
    }

    public function auditLabel(): string
    {
        return "{$this->enrollment_number} · {$this->fullName()}";
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    public function nextBirthday(): ?Carbon
    {
        if ($this->birth_date === null) {
            return null;
        }

        $birthday = $this->birth_date->copy()->setYear(now()->year);

        return $birthday->isPast() && ! $birthday->isToday() ? $birthday->addYear() : $birthday;
    }

    public function hasPhoto(): bool
    {
        return $this->photo_path !== null;
    }

    /** ¿Ya se le puede enviar el acceso? (tiene usuario y aún no crea su contraseña). */
    public function canReceiveAccess(): bool
    {
        return $this->user !== null && $this->status->canLogIn();
    }

    /** Un alumno con inscripciones o historial se da de baja, no se elimina. */
    public function canBeDeleted(): bool
    {
        return ! $this->enrollments()->exists();
    }

    /** La ruta privada de la foto no se guarda en la bitácora; solo que cambió. */
    protected function auditRedact(): array
    {
        return ['photo_path'];
    }
}
