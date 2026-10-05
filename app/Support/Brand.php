<?php

namespace App\Support;

use App\Models\School;

/**
 * Identidad visual activa: la de la escuela del usuario conectado o, si no
 * tiene escuela (Super Admin, login), la de la plataforma.
 * Las propiedades brand_primary / brand_accent son las que lee
 * layouts/partials/brand.blade.php.
 */
final readonly class Brand
{
    public function __construct(
        public string $name,
        public string $monogram,
        public ?string $logo_url,
        public string $brand_primary,
        public string $brand_accent,
    ) {}

    public static function current(): self
    {
        $school = auth()->user()?->loadMissing('school')->school;

        return $school ? self::forSchool($school) : self::platform();
    }

    public static function forSchool(School $school): self
    {
        return new self(
            name: $school->name,
            monogram: $school->monogram(),
            logo_url: $school->logoUrl(),
            brand_primary: $school->brand_primary,
            brand_accent: $school->brand_accent ?? config('appingles.brand.accent'),
        );
    }

    public static function platform(): self
    {
        return new self(
            name: config('appingles.brand.name'),
            monogram: config('appingles.brand.monogram'),
            logo_url: null,
            brand_primary: config('appingles.brand.primary'),
            brand_accent: config('appingles.brand.accent'),
        );
    }
}
