<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Bitácora de cambios: quién, cuándo y qué cambió. Solo se inserta y se
 * conserva para siempre (RN-12): no hay edición ni borrado en ninguna pantalla.
 */
#[Fillable(['user_id', 'school_id', 'auditable_type', 'auditable_id', 'auditable_label', 'event', 'old_values', 'new_values', 'ip_address'])]
class AuditLog extends Model
{
    use BelongsToSchool;

    public const UPDATED_AT = null;

    /** Valor guardado en lugar de un dato sensible (contraseña, ruta privada). */
    public const REDACTED = '[redacted]';

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    private static bool $muted = false;

    /**
     * Ejecuta sin registrar eventos individuales (copias masivas); quien
     * llama registra después un solo evento resumen.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function muted(Closure $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(Model $model, string $event, ?array $old = null, ?array $new = null): ?self
    {
        if (self::$muted) {
            return null;
        }

        $actor = auth()->user();

        return self::create([
            'user_id' => $actor?->id,
            'school_id' => $model instanceof School ? $model->getKey() : ($model->getAttribute('school_id') ?? $actor?->school_id),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'auditable_label' => self::labelFor($model),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
        ]);
    }

    private static function labelFor(Model $model): ?string
    {
        $label = method_exists($model, 'auditLabel')
            ? $model->auditLabel()
            : $model->getAttribute('name') ?? $model->getAttribute('label') ?? $model->getAttribute('code');

        return $label === null || $label === '' ? null : Str::limit((string) $label, 157);
    }

    /**
     * Módulos que se pueden filtrar: clave (snake_case del modelo) => nombre.
     *
     * @return array<string, string>
     */
    public static function moduleOptions(): array
    {
        return collect(Lang::get('audit.models'))->sort()->all();
    }

    /** Clase del modelo para una clave de módulo válida. */
    public static function modelClassFor(string $module): ?string
    {
        return array_key_exists($module, self::moduleOptions()) ? 'App\\Models\\'.Str::studly($module) : null;
    }

    public function moduleKey(): string
    {
        return Str::snake(class_basename($this->auditable_type));
    }

    public function moduleLabel(): string
    {
        $key = 'audit.models.'.$this->moduleKey();

        return Lang::has($key) ? __($key) : class_basename($this->auditable_type);
    }

    public function eventLabel(): string
    {
        $key = 'audit.events.'.$this->event;

        return Lang::has($key) ? __($key) : Str::headline($this->event);
    }

    /** success = alta, warning = cambio, danger = baja; lo demás, informativo. */
    public function eventBadge(): string
    {
        return match ($this->event) {
            'created', 'activated', 'enabled' => 'success',
            'updated', 'access_updated' => 'warning',
            'deleted', 'deactivated', 'disabled' => 'danger',
            default => 'info',
        };
    }

    /** Nombre del registro afectado (aunque ya no exista). */
    public function subject(): string
    {
        return $this->auditable_label
            ?? $this->new_values['name'] ?? $this->old_values['name']
            ?? $this->new_values['label'] ?? $this->old_values['label']
            ?? '#'.$this->auditable_id;
    }

    /**
     * Campos que cambiaron, listos para mostrar.
     *
     * @return list<array{field: string, old: string, new: string}>
     */
    public function changes(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        return collect(array_keys($old + $new))
            ->map(fn (string $field) => [
                'field' => self::fieldLabel($field),
                'old' => array_key_exists($field, $old) ? self::formatValue($old[$field]) : '',
                'new' => array_key_exists($field, $new) ? self::formatValue($new[$field]) : '',
            ])
            ->values()
            ->all();
    }

    public static function fieldLabel(string $field): string
    {
        $key = 'audit.fields.'.$field;

        return Lang::has($key) ? __($key) : Str::of($field)->replace('_', ' ')->ucfirst()->toString();
    }

    public static function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            $value === self::REDACTED => __('audit.redacted'),
            is_bool($value) => __($value ? 'audit.yes' : 'audit.no'),
            is_array($value) => self::formatList($value),
            default => Str::limit((string) $value, 300),
        };
    }

    /** @param  array<mixed>  $value */
    private static function formatList(array $value): string
    {
        if (array_is_list($value) && collect($value)->every(fn (mixed $item) => is_scalar($item))) {
            return $value === [] ? '—' : implode(', ', $value);
        }

        return Str::limit((string) json_encode($value, JSON_UNESCAPED_UNICODE), 300);
    }
}
