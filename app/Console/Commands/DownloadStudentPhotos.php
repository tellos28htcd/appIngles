<?php

namespace App\Console\Commands;

use App\Jobs\DownloadStudentPhoto;
use App\Models\School;
use App\Models\Student;
use Illuminate\Console\Command;

/**
 * Vuelve a mandar a la cola la descarga de fotos de los alumnos que tienen
 * enlace (importado) y aún no tienen foto. Requiere `php artisan queue:work`.
 * Uso: php artisan students:download-photos [--school=026]
 */
class DownloadStudentPhotos extends Command
{
    protected $signature = 'students:download-photos {--school= : Clave de la escuela (todas si se omite)}';

    protected $description = 'Encola la descarga de las fotos pendientes de los alumnos (enlace sin foto)';

    public function handle(): int
    {
        $schoolId = null;

        if ($this->option('school') !== null) {
            $schoolId = School::where('code', $this->option('school'))->value('id');

            if ($schoolId === null) {
                $this->error("No existe la escuela con clave «{$this->option('school')}».");

                return self::FAILURE;
            }
        }

        $count = 0;

        Student::withoutGlobalScope('school')
            ->whereNotNull('photo_source_url')
            ->whereNull('photo_path')
            ->when($schoolId !== null, fn ($query) => $query->where('school_id', $schoolId))
            ->select('id')
            ->chunkById(500, function ($students) use (&$count): void {
                foreach ($students as $student) {
                    DownloadStudentPhoto::dispatch($student->id);
                    $count++;
                }
            });

        $this->info("Fotos en cola: {$count}. Se descargan mientras corre `php artisan queue:work`.");

        return self::SUCCESS;
    }
}
