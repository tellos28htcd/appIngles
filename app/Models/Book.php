<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Libro = nivel del programa (Beginners, Intermediate, Advanced). */
#[Fillable(['school_id', 'base_id', 'level', 'name', 'is_active'])]
class Book extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<Club, $this> */
    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class)->withPivot('hours');
    }

    public function canBeDeleted(): bool
    {
        return $this->lessons()->doesntExist();
    }
}
