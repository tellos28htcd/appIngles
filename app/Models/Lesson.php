<?php

namespace App\Models;

use App\Enums\LessonType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lección de un libro: Lesson, Check Point o Verb in context. */
#[Fillable(['school_id', 'base_id', 'book_id', 'number', 'name', 'type', 'sort_order', 'is_active'])]
class Lesson extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'type' => LessonType::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function canBeDeleted(): bool
    {
        return true;
    }
}
