<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Registra en audit_logs cada alta, cambio y baja del modelo (RN-12).
 * Las copias masivas se envuelven en AuditLog::muted() y se registran
 * como un solo evento resumen.
 *
 * Cada modelo puede definir:
 * - auditExcept(): campos que nunca se registran (contadores, tokens).
 * - auditRedact(): campos sensibles; solo se registra que cambiaron.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => AuditLog::record($model, 'created', null, $model->auditableValues()));

        static::updated(function ($model): void {
            $changes = $model->auditableChanges();

            if ($changes !== []) {
                AuditLog::record($model, 'updated', $model->auditableFilter(array_intersect_key($model->getOriginal(), $changes)), $changes);
            }
        });

        static::deleted(fn ($model) => AuditLog::record($model, 'deleted', $model->auditableValues()));
    }

    /** @return list<string> */
    protected function auditExcept(): array
    {
        return [];
    }

    /** @return list<string> */
    protected function auditRedact(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function auditableValues(): array
    {
        return $this->auditableFilter($this->attributesToArray());
    }

    /** @return array<string, mixed> */
    protected function auditableChanges(): array
    {
        return $this->auditableFilter($this->getChanges());
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditableFilter(array $values): array
    {
        $values = array_diff_key($values, array_flip(['created_at', 'updated_at', ...$this->auditExcept()]));

        foreach ($this->auditRedact() as $attribute) {
            if (isset($values[$attribute])) {
                $values[$attribute] = AuditLog::REDACTED;
            }
        }

        return $values;
    }
}
