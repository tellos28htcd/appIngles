<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * El archivo debe ser de verdad una imagen JPG, PNG o WebP: se revisa su contenido
 * (no el nombre ni el tipo que declara el navegador). Así un archivo con código
 * renombrado a .jpg se rechaza aunque diga ser imagen.
 */
class RealImage implements ValidationRule
{
    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $content = (string) $value->get();
        $info = $content === '' ? false : @getimagesizefromstring($content);

        if ($info === false || ! in_array($info[2], self::TYPES, true) || $info[0] < 1 || $info[1] < 1 || str_contains($content, '<?php')) {
            $fail(__('uploads.real_image'));
        }
    }
}
