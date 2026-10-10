<?php

namespace App\Models;

use App\Enums\FailedActivityPolicy;
use App\Enums\MaxSessionsScope;
use App\Enums\SchoolStatus;
use App\Enums\SelfBooking;
use App\Models\Concerns\Auditable;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Escuela = plantel = tenant. Todo dato transaccional lleva school_id.
 */
#[Fillable([
    'code', 'name',
    'street', 'exterior_number', 'interior_number', 'neighborhood', 'postal_code', 'state_id', 'municipality_id',
    'phone', 'rfc', 'legal_name',
    'last_folio_series_a', 'last_folio_series_b',
    'logo_path', 'brand_primary', 'brand_accent',
    'session_capacity', 'timezone', 'currency',
    'works_sundays', 'schedules_classrooms', 'books_without_classroom', 'hybrid_clubs', 'requires_progress',
    'self_booking', 'max_sessions_scope', 'max_sessions', 'failed_activity_policy', 'club_min_lesson_number',
    'status',
])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use Auditable, HasFactory;

    public const MAX_SESSION_CAPACITY = 6;

    protected function casts(): array
    {
        return [
            'status' => SchoolStatus::class,
            'self_booking' => SelfBooking::class,
            'max_sessions_scope' => MaxSessionsScope::class,
            'failed_activity_policy' => FailedActivityPolicy::class,
            'works_sundays' => 'boolean',
            'schedules_classrooms' => 'boolean',
            'books_without_classroom' => 'boolean',
            'hybrid_clubs' => 'boolean',
            'requires_progress' => 'boolean',
            'session_capacity' => 'integer',
            'max_sessions' => 'integer',
            'club_min_lesson_number' => 'integer',
            'last_folio_series_a' => 'integer',
            'last_folio_series_b' => 'integer',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Shift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /** @return HasMany<Book, $this> */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
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

    /** Los consecutivos de folio avanzan con cada recibo; no son un cambio de configuración. */
    protected function auditExcept(): array
    {
        return ['last_folio_series_a', 'last_folio_series_b'];
    }

    public function isActive(): bool
    {
        return $this->status === SchoolStatus::Active;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function monogram(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 1)) ?: 'E';
    }
}
