<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Movimiento de una reservación (reservó, lista de espera, cambió de aula, asistió, canceló…); solo se inserta. */
#[Fillable(['school_id', 'booking_id', 'event', 'detail', 'user_id', 'created_at'])]
class BookingEvent extends Model
{
    use BelongsToSchool;

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
