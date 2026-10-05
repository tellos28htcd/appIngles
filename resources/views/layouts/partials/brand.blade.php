{{-- Incluir dentro de <head>, ANTES de @vite. Inyecta los colores de la escuela (tenant). --}}
@php
    use App\Support\BrandColor;
    $primary = $school->brand_primary ?? '#4361EE';
    $accent  = $school->brand_accent  ?? '#FFC23D';
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Figtree:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
    :root {
        --brand-primary: {{ $primary }};
        --brand-accent: {{ $accent }};
        --brand-on-primary: {{ BrandColor::onPrimary($primary) }};
        --brand-on-accent: {{ BrandColor::onColor($accent) }};
    }
</style>
