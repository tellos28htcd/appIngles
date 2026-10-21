<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Cuenta bancaria de la escuela donde los alumnos depositan sus pagos. */
#[Fillable(['school_id', 'name', 'bank', 'account_holder', 'account_number', 'clabe', 'is_active'])]
class BankAccount extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Nombre para listas: alias · banco · terminación de la cuenta. */
    public function label(): string
    {
        $number = $this->account_number ?? $this->clabe;

        return collect([$this->name, $this->bank, $number ? '…'.substr($number, -4) : null])->filter()->unique()->implode(' · ');
    }

    /** Si alguna ficha de inscripción la usa, solo se desactiva. */
    public function canBeDeleted(): bool
    {
        return ! StudentEnrollment::withoutGlobalScope('school')->where('bank_account_id', $this->id)->exists();
    }
}
