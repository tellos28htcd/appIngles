<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Club con horas por nivel (book_club.hours). */
#[Fillable(['school_id', 'base_id', 'name', 'description', 'sort_order', 'is_active'])]
class Club extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsToMany<Book, $this> */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)->withPivot('hours');
    }

    public function canBeDeleted(): bool
    {
        return true;
    }
}
