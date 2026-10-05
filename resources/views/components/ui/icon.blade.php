{{-- Iconos de línea (24 px, trazo 2) del sistema de diseño.
     Uso: <x-ui.icon name="calendar" class="size-5" />
     El nombre se guarda en menu_items.icon. --}}
@props(['name'])
@php
$paths = [
    'home' => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
    'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="3"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
    'student' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.5-6 8-6s7 2 8 6"/>',
    'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.4 3.4-5.5 6.5-5.5s5.7 2.1 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 010 6.8M18 14.8c1.8.8 3 2.6 3.5 5.2"/>',
    'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="3"/><path d="M9 4V3h6v1M9 12.5l2 2 4-4"/>',
    'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
    'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/>',
    'building' => '<path d="M4 21V5a2 2 0 012-2h8a2 2 0 012 2v16M16 9h2a2 2 0 012 2v10M2 21h20"/><path d="M8 7h4M8 11h4M8 15h4"/>',
    'heart' => '<path d="M12 20s-7.5-4.6-9.2-9.3C1.6 7.2 3.9 4 7.2 4c2 0 3.5 1.1 4.8 2.8C13.3 5.1 14.8 4 16.8 4c3.3 0 5.6 3.2 4.4 6.7C19.5 15.4 12 20 12 20z"/>',
    'logout' => '<path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3"/><path d="M10 16l-4-4 4-4M6 12h10"/>',
    'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
    'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
    'alert' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/>',
    'check' => '<path d="M5 12.5l4.5 4.5L19 7"/>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M4 7l8 6 8-6"/>',
    'sparkles' => '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
];
@endphp
<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? $paths['sparkles'] !!}</svg>
