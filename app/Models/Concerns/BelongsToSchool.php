<?php

namespace App\Models\Concerns;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aislamiento multi-escuela (RN-11).
 * - Un usuario de escuela solo ve y crea registros de su escuela.
 * - El Super Admin no tiene escuela: ve todo, y cada pantalla filtra
 *   explícitamente (p. ej. catálogo base = school_id nulo).
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $query): void {
            $user = auth()->user();

            if ($user !== null && $user->school_id !== null) {
                $query->where($query->qualifyColumn('school_id'), $user->school_id);
            }
        });

        static::creating(function ($model): void {
            $user = auth()->user();

            if ($model->school_id === null && $user?->school_id !== null) {
                $model->school_id = $user->school_id;
            }
        });
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Registros de un catálogo: el base (null) o el de una escuela.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOfCatalog(Builder $query, ?int $schoolId): void
    {
        $schoolId === null
            ? $query->whereNull($query->qualifyColumn('school_id'))
            : $query->where($query->qualifyColumn('school_id'), $schoolId);
    }
}
