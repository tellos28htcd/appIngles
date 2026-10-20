<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Enums\StudentTermMovementType;
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
    'first_name', 'last_name', 'second_last_name', 'mobile_phone', 'landline_phone', 'email', 'birth_date',
    'address', 'street', 'exterior_number', 'interior_number', 'neighborhood', 'state_id', 'municipality_id', 'postal_code', 'address_needs_review',
    'course_starts_on', 'withdrawn_on', 'graduated_on',
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
            'course_starts_on' => 'date',
            'withdrawn_on' => 'date',
            'graduated_on' => 'date',
            'address_needs_review' => 'boolean',
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

    /** @return HasMany<StudentTermMovement, $this> */
    public function termMovements(): HasMany
    {
        return $this->hasMany(StudentTermMovement::class)->orderBy('occurred_on')->orderBy('id');
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

    /** @return BelongsTo<State, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** Días del programa (1 año); cada reinscripción inicia otro ciclo de 365 días. */
    public const PROGRAM_DAYS = 365;

    /** Días de solicitud de ausencia que se pueden autorizar por ciclo del programa. */
    public const MAX_ABSENCE_DAYS = 60;

    /** Inicio del ciclo vigente (para las ausencias): la última reinscripción o, si no hay, el inicio del curso. */
    public function cycleStartsOn(): ?Carbon
    {
        $lastReenrollment = $this->termMovements->where('type', StudentTermMovementType::Reenrollment)->max('occurred_on');

        return $lastReenrollment ? Carbon::parse($lastReenrollment) : $this->course_starts_on?->copy();
    }

    /** Días de extensión de tiempo acumulados. */
    public function extensionDays(): int
    {
        return (int) $this->termMovements->where('type', StudentTermMovementType::Extension)->sum('days');
    }

    /**
     * Fin del curso: inicio del curso + 365 días + los días de los movimientos del plazo
     * (reinscripción, extensión, ajuste, mensualidad abierta). Las ausencias no lo recorren.
     */
    public function courseEndsOn(): ?Carbon
    {
        return $this->course_starts_on?->copy()->addDays(self::PROGRAM_DAYS + (int) $this->termMovements->sum('days'));
    }

    /** Fin del curso antes de un movimiento (inicio + 365 + los movimientos anteriores a él). */
    public function courseEndsBefore(StudentTermMovement $movement): ?Carbon
    {
        $previous = $this->termMovements->filter(fn (StudentTermMovement $item) => $item->occurred_on->lt($movement->occurred_on)
            || ($item->occurred_on->eq($movement->occurred_on) && $item->id < $movement->id));

        return $this->course_starts_on?->copy()->addDays(self::PROGRAM_DAYS + (int) $previous->sum('days'));
    }

    /** Mensualidad abierta sin cerrar (el fin del curso es estimado mientras exista). */
    public function openMonthly(): ?StudentTermMovement
    {
        return $this->termMovements->first(fn (StudentTermMovement $movement) => $movement->isOpen());
    }

    /** Días de solicitud de ausencia acumulados (todos los ciclos). */
    public function absenceDaysTotal(): int
    {
        return (int) $this->absences->sum(fn (StudentAbsence $absence) => $absence->days());
    }

    /** Días de solicitud de ausencia del ciclo vigente (máximo 60). */
    public function absenceDaysInCycle(?int $exceptAbsenceId = null): int
    {
        $start = $this->cycleStartsOn();

        return (int) $this->absences
            ->reject(fn (StudentAbsence $absence) => $absence->id === $exceptAbsenceId)
            ->filter(fn (StudentAbsence $absence) => $start === null || $absence->starts_on->gte($start))
            ->sum(fn (StudentAbsence $absence) => $absence->days());
    }

    /** Domicilio en una línea, a partir de sus campos. */
    public function formattedAddress(): ?string
    {
        if ($this->street === null) {
            return $this->address;
        }

        $line = trim($this->street.' '.($this->exterior_number ? 'No. '.$this->exterior_number : ''));
        $line .= $this->interior_number ? ' Int. '.$this->interior_number : '';

        return collect([$line, $this->neighborhood ? 'Col. '.$this->neighborhood : null, $this->municipality?->name, $this->state?->name,
            $this->postal_code ? 'C.P. '.$this->postal_code : null])->filter()->implode(', ');
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
