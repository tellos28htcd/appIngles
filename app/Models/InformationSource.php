<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Origen de la información: cómo llegó el alumno (Facebook, información presencial, propia…). */
#[Fillable(['school_id', 'base_id', 'name', 'channel', 'short_name', 'is_active'])]
class InformationSource extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function canBeDeleted(): bool
    {
        return $this->school_id === null || ! Student::withoutGlobalScope('school')->where('information_source_id', $this->id)->exists();
    }
}
