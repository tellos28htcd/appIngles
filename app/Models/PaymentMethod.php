<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Método de pago de la escuela (efectivo, transferencia, tarjeta, cheque…). */
#[Fillable(['school_id', 'base_id', 'name', 'requires_reference', 'is_active'])]
class PaymentMethod extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Se podrá eliminar mientras ningún pago lo use. */
    public function canBeDeleted(): bool
    {
        return true;
    }
}
