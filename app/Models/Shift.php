<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Turno (Matutino, Vespertino, Sabatino). */
#[Fillable(['school_id', 'base_id', 'name', 'sort_order', 'is_active'])]
class Shift extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
