<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\TeacherStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Teacher de una escuela; entra al sistema con su usuario (rol Teacher). */
#[Fillable([
    'school_id', 'user_id',
    'first_name', 'last_name', 'second_last_name', 'curp', 'rfc', 'photo_path',
    'street', 'exterior_number', 'interior_number', 'neighborhood', 'postal_code', 'state_id', 'municipality_id',
    'contract_type', 'weekly_hours', 'status',
])]
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use Auditable, BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'contract_type' => ContractType::class,
            'weekly_hours' => 'integer',
            'status' => TeacherStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->last_name, $this->second_last_name])));
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /** Ficha creada desde Usuarios sin datos de contratación todavía. */
    public function isIncomplete(): bool
    {
        return $this->contract_type === null;
    }

    public function hasPhoto(): bool
    {
        return $this->photo_path !== null;
    }

    /** Se podrá eliminar mientras no tenga sesiones ni evaluaciones (Agenda / Seguimiento). */
    public function canBeDeleted(): bool
    {
        return ! $this->user?->hasModuleRecords();
    }

    /** @return array{first_name: string, last_name: string} */
    public static function splitName(string $fullName): array
    {
        $parts = Str::of(trim($fullName))->explode(' ')->filter()->values();

        return [
            'first_name' => (string) ($parts->first() ?? $fullName),
            'last_name' => (string) ($parts->slice(1)->implode(' ') ?: '—'),
        ];
    }
}
