<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para toda respuesta web:
 * - Content-Security-Policy con nonce: solo se ejecutan los scripts del propio
 *   sistema (Vite, Livewire); un <script> inyectado no corre.
 * - Sin incrustar el sitio en iframes (clickjacking), sin adivinar tipos MIME,
 *   referer mínimo, sin cámara/micrófono/geolocalización, HSTS en HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->contentSecurityPolicy(Vite::cspNonce()),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        // Servidor de desarrollo de Vite (npm run dev): solo existe en local.
        $vite = Vite::isRunningHot() ? trim((string) file_get_contents(public_path('hot'))) : '';
        $viteWs = $vite !== '' ? preg_replace('#^http#', 'ws', $vite) : '';

        $directives = [
            'default-src' => ["'self'"],
            // 'unsafe-eval' lo requiere Alpine (incluido en Livewire) para evaluar x-data/x-on.
            'script-src' => ["'self'", "'nonce-{$nonce}'", "'unsafe-eval'", $vite],
            // Los colores de marca se inyectan como variables CSS en atributos style.
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', $vite],
            'font-src' => ["'self'", 'https://fonts.gstatic.com', 'data:'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'", $vite, $viteWs],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive) => trim($directive.' '.implode(' ', array_filter($sources))))
            ->implode('; ');
    }
}
