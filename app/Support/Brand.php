<?php

namespace App\Support;

/**
 * Identidad visual activa. Mientras no existe el módulo de escuelas solo hay
 * la marca de la plataforma; después se resolverá la de la escuela del usuario.
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
        return once(fn () => self::platform());
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
