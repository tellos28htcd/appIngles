<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Entidad federativa (catálogo INEGI). */
class State extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return HasMany<Municipality, $this> */
    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class)->orderBy('name');
    }
}
