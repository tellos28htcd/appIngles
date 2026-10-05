<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de cambios: quién, cuándo y qué cambió. Solo se inserta (RN-12).
 */
#[Fillable(['user_id', 'school_id', 'auditable_type', 'auditable_id', 'event', 'old_values', 'new_values', 'ip_address'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

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

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(Model $model, string $event, ?array $old = null, ?array $new = null): self
    {
        $actor = auth()->user();

        return self::create([
            'user_id' => $actor?->id,
            'school_id' => $model->getAttribute('school_id') ?? $actor?->school_id,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
        ]);
    }
}
