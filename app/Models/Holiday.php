<?php

namespace App\Models;

use App\Enums\HolidayType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Día o periodo sin clase (RN-22): no se agendan sesiones ni cuenta para la asistencia. */
#[Fillable(['school_id', 'base_id', 'starts_on', 'ends_on', 'name', 'type', 'official_key', 'is_active'])]
class Holiday extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'type' => HolidayType::class,
            'is_active' => 'boolean',
        ];
    }

    public function isOfficial(): bool
    {
        return $this->type === HolidayType::Official;
    }

    /** Los oficiales no se eliminan, solo se desactivan. */
    public function canBeDeleted(): bool
    {
        return ! $this->isOfficial();
    }

    public function days(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }

    /**
     * Días sin clase activos que cubren la fecha (para Agenda y asistencia).
     *
     * @param  Builder<Holiday>  $query
     */
    public function scopeCovering(Builder $query, Carbon|string $date): void
    {
        $day = Carbon::parse($date)->toDateString();

        $query->where('is_active', true)
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day);
    }

    /** @param Builder<Holiday> $query */
    public function scopeInYear(Builder $query, int $year): void
    {
        $query->whereYear('starts_on', $year);
    }
}
