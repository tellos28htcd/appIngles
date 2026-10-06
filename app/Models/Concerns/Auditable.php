<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Registra en audit_logs cada alta, cambio y baja del modelo (RN-12).
 * Las copias masivas se envuelven en AuditLog::muted() y se registran
 * como un solo evento resumen.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => AuditLog::record($model, 'created', null, $model->auditableValues()));

        static::updated(function ($model): void {
            $changes = $model->auditableChanges();

            if ($changes !== []) {
                AuditLog::record($model, 'updated', array_intersect_key($model->getOriginal(), $changes), $changes);
            }
        });

        static::deleted(fn ($model) => AuditLog::record($model, 'deleted', $model->auditableValues()));
    }

    /** @return array<string, mixed> */
    protected function auditableValues(): array
    {
        return array_diff_key($this->attributesToArray(), array_flip(['created_at', 'updated_at']));
    }

    /** @return array<string, mixed> */
    protected function auditableChanges(): array
    {
        return array_diff_key($this->getChanges(), array_flip(['created_at', 'updated_at']));
    }
}
