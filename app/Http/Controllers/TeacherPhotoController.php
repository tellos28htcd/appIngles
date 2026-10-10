<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sirve la foto (privada) de un teacher solo a quien tiene permiso de verla. */
class TeacherPhotoController extends Controller
{
    public function __invoke(Teacher $teacher): StreamedResponse
    {
        Gate::authorize('viewPhoto', $teacher);

        abort_unless($teacher->photo_path && Storage::disk('local')->exists($teacher->photo_path), 404);

        return Storage::disk('local')->response($teacher->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
