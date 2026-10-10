<?php

namespace App\Models;

use App\Enums\ChargeConceptType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Concepto de cobro (colegiatura, inscripción, libro…) con monto sugerido. */
#[Fillable(['school_id', 'base_id', 'name', 'type', 'suggested_amount', 'is_active'])]
class ChargeConcept extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'type' => ChargeConceptType::class,
            'suggested_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Se podrá eliminar mientras ningún cargo lo use. */
    public function canBeDeleted(): bool
    {
        return true;
    }
}
