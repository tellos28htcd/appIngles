<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sirve la foto (privada) de un alumno solo a quien tiene permiso de verla. */
class StudentPhotoController extends Controller
{
    public function __invoke(Student $student): StreamedResponse
    {
        Gate::authorize('viewPhoto', $student);

        abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

        return Storage::disk('local')->response($student->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
