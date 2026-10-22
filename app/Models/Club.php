<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Club con horas por nivel (book_club.hours) y sus abreviaturas para la programación
 * mensual: [{code: 'C12', levels: [1, 2], color: 1, is_active: true}, …].
 */
#[Fillable(['school_id', 'base_id', 'name', 'description', 'codes', 'sort_order', 'is_active'])]
class Club extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'codes' => 'array'];
    }

    /** @return list<array{code: string, levels: list<int>, color: int, is_active: bool}> */
    public function codeList(): array
    {
        return array_values($this->codes ?? []);
    }

    /** @return array{code: string, levels: list<int>, color: int, is_active: bool}|null */
    public function codeInfo(string $code): ?array
    {
        return collect($this->codeList())->first(fn (array $item) => strcasecmp($item['code'], $code) === 0);
    }

    /** @return BelongsToMany<Book, $this> */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)->withPivot('hours');
    }

    /** Si ya tiene clubs programados, solo se desactiva. */
    public function canBeDeleted(): bool
    {
        return ! ClubSession::withoutGlobalScope('school')->where('club_id', $this->id)->exists();
    }
}
