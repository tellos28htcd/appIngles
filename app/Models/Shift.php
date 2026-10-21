<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Turno (Matutino, Vespertino, Sabatino, Domingo) con los días en que aplica (1 = lunes … 7 = domingo). */
#[Fillable(['school_id', 'base_id', 'name', 'weekdays', 'sort_order', 'is_active'])]
class Shift extends Model
{
    use Auditable, BelongsToSchool;

    public const DEFAULT_DAYS = [1, 2, 3, 4, 5, 6];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'weekdays' => 'array'];
    }

    /** Días en que aplica (sin capturar: lunes a sábado). @return list<int> */
    public function days(): array
    {
        return $this->weekdays ?: self::DEFAULT_DAYS;
    }

    public function appliesOn(int $weekday): bool
    {
        return in_array($weekday, $this->days(), true);
    }

    /** @return HasMany<ScheduleSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class);
    }

    public function canBeDeleted(): bool
    {
        return $this->slots()->doesntExist();
    }
}
