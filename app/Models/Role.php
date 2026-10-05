<?php

namespace App\Models;

use App\Enums\RoleScope;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'description', 'scope', 'sort_order', 'is_active'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public const PLATFORM_ADMIN = 'platform_admin';

    public const SCHOOL_ADMIN = 'school_admin';

    public const STUDENT_SERVICES = 'student_services';

    public const ACADEMIC_COORDINATOR = 'academic_coordinator';

    public const TEACHER = 'teacher';

    public const MENTOR = 'mentor';

    public const RECEPTION = 'reception';

    public const GUARDIAN = 'guardian';

    protected function casts(): array
    {
        return [
            'scope' => RoleScope::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return BelongsToMany<MenuItem, $this> */
    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class);
    }
}
