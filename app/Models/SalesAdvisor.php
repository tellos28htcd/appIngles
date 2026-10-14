<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Asesor de ventas de la escuela (también canales como INTERCAMBIO). */
#[Fillable(['school_id', 'advisor_position_id', 'name', 'hired_on', 'left_on', 'is_active'])]
class SalesAdvisor extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'hired_on' => 'date',
            'left_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<AdvisorPosition, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(AdvisorPosition::class, 'advisor_position_id');
    }

    public function canBeDeleted(): bool
    {
        return ! StudentEnrollment::withoutGlobalScope('school')->where('sales_advisor_id', $this->id)->exists();
    }
}
