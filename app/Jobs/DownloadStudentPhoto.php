<?php

namespace App\Jobs;

use App\Models\Student;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Descarga la foto del alumno desde el enlace importado (p. ej. Google Drive)
 * y la guarda en el almacenamiento privado. Solo HTTPS, sin direcciones
 * internas, solo imágenes JPG/PNG/WebP de hasta 5 MB. Si falla, la foto se
 * sube desde la pantalla.
 */
class DownloadStudentPhoto implements ShouldQueue
{
    use Queueable;

    public const MAX_BYTES = 5 * 1024 * 1024;

    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $studentId) {}

    public function handle(): void
    {
        $student = Student::withoutGlobalScope('school')->find($this->studentId);

        if ($student === null || $student->photo_path !== null || $student->photo_source_url === null) {
            return;
        }

        $url = self::directDownloadUrl($student->photo_source_url);
        $ip = self::safeAddress($url);

        if ($ip === null) {
            Log::warning('Foto de alumno omitida: enlace no permitido.', ['student_id' => $student->id]);

            return;
        }

        try {
            // Google Drive redirige a su servidor de contenido: cada salto se vuelve a validar (máx. 3).
            // La conexión se fija a la IP pública validada (CURLOPT_RESOLVE): un cambio de DNS entre la
            // validación y la descarga no puede llevarla a la red interna del servidor.
            for ($hops = 0; ; $hops++) {
                $host = (string) parse_url($url, PHP_URL_HOST);
                $response = Http::timeout(20)->withoutRedirecting()
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$ip}"]]])
                    ->get($url);
                $url = (string) $response->header('Location');

                if (! $response->redirect() || $hops >= 3 || ($ip = self::safeAddress($url)) === null) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo descargar la foto del alumno.', ['student_id' => $student->id, 'error' => $exception->getMessage()]);

            return;
        }

        $body = $response->body();
        $info = $response->successful() && strlen($body) > 0 && strlen($body) <= self::MAX_BYTES ? @getimagesizefromstring($body) : false;
        $extension = $info ? (self::EXTENSIONS[$info['mime']] ?? null) : null;

        if ($extension === null) {
            Log::warning('Foto de alumno omitida: la respuesta no es una imagen válida.', ['student_id' => $student->id, 'status' => $response->status()]);

            return;
        }

        $path = "students/{$student->school_id}/photo-{$student->id}-".Str::lower(Str::random(12)).".{$extension}";
        Storage::disk('local')->put($path, $body);
        $student->update(['photo_path' => $path]);
    }

    /** Un enlace de vista de Google Drive se convierte en enlace de descarga directa. */
    public static function directDownloadUrl(string $url): string
    {
        if (preg_match('#drive\.google\.com/(?:file/d/|uc\?(?:.*&)?id=|open\?id=)([A-Za-z0-9_\-]+)#', $url, $matches)) {
            return 'https://drive.google.com/uc?export=download&id='.$matches[1];
        }

        return $url;
    }

    /** Solo HTTPS hacia direcciones públicas (evita que un enlace apunte a la red interna del servidor). */
    public static function isSafeUrl(string $url): bool
    {
        return self::safeAddress($url) !== null;
    }

    /**
     * IP pública a la que se conecta (o null si el enlace no se permite): HTTPS, sin usuario ni
     * puerto, sin IP escrita directamente y con todas sus direcciones públicas.
     */
    public static function safeAddress(string $url): ?string
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['port'])
            || filter_var(trim($parts['host'], '[]'), FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $ips = gethostbynamel($parts['host']) ?: [];
        $public = $ips !== [] && collect($ips)->every(fn (string $ip) => filter_var(
            $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false);

        return $public ? $ips[0] : null;
    }
}
