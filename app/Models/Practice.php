<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Práctica de la ruta de avance (catálogo "Prácticas", Excel Cat_Practicas.xlsx): libro + lección + actividad,
 * con su orden (consecutivo en que se asignan en la agenda y se califican en el Kardex) y sus puntos acumulados.
 * El nombre propio (p. ej. "DIRECT P.") sustituye al código de la actividad en esa lección. Las marcadas como
 * presentación (A7 de las lecciones 15 y 17) se agendan como Presentación y pueden quedar pendientes en el Kardex.
 */
#[Fillable(['school_id', 'base_id', 'book_id', 'lesson_id', 'activity_number', 'name', 'is_presentation', 'sort_order', 'points', 'is_active'])]
class Practice extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'activity_number' => 'integer',
            'sort_order' => 'integer',
            'points' => 'integer',
            'is_presentation' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Nombre de la práctica como en el Excel, p. ej. "L1-L1-A2-1P" o "L1-CP1-A6-DIRECT P.".
     *
     * @param  array<int, string>  $codes  número de actividad => código (BA, 1P…)
     */
    public static function labelFor(int $level, Lesson $lesson, int $activityNumber, ?string $name, array $codes): string
    {
        $code = $name ?: ($codes[$activityNumber] ?? null);

        return "L{$level}-".$lesson->shortCode()."-A{$activityNumber}".($code ? '-'.$code : '');
    }

    /**
     * Nombres propios de las prácticas de la escuela por "lección|actividad" (se consulta una vez por petición).
     *
     * @return array<string, string>
     */
    public static function namesFor(int $schoolId): array
    {
        return once(fn (): array => self::withoutGlobalScope('school')->where('school_id', $schoolId)->whereNotNull('name')->get(['lesson_id', 'activity_number', 'name'])
            ->mapWithKeys(fn (Practice $practice) => [$practice->lesson_id.'|'.$practice->activity_number => $practice->name])->all());
    }

    /** ¿La escuela tiene su catálogo de prácticas? (si no, la ruta se arma lección por lección). */
    public static function existsFor(int $schoolId): bool
    {
        return once(fn (): bool => self::withoutGlobalScope('school')->where('school_id', $schoolId)->exists());
    }

    public function canBeDeleted(): bool
    {
        return true;
    }

    public function auditLabel(): string
    {
        return 'Orden '.$this->sort_order;
    }
}
