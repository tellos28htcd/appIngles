<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Student;
use App\Support\AddressParser;
use Illuminate\Console\Command;

/**
 * Separa el domicilio de una línea de los alumnos (importado del Excel) en
 * calle, números, colonia, municipio, estado y C.P. Solo toca a los alumnos
 * que aún no tienen calle capturada. Con --dry-run solo muestra el resultado.
 * Uso: php artisan students:split-addresses [--school=026] [--dry-run]
 */
class SplitStudentAddresses extends Command
{
    protected $signature = 'students:split-addresses {--school= : Clave de la escuela (todas si se omite)} {--dry-run : Solo mostrar, sin guardar}';

    protected $description = 'Separa el domicilio de los alumnos en calle, números, colonia, municipio, estado y C.P.';

    public function handle(): int
    {
        $schools = School::query()->when($this->option('school'), fn ($query, $code) => $query->where('code', $code))->get();
        $totals = ['separados' => 0, 'revisar' => 0];

        foreach ($schools as $school) {
            $parser = new AddressParser($school->state_id);

            Student::withoutGlobalScope('school')->where('school_id', $school->id)
                ->whereNotNull('address')->whereNull('street')
                ->each(function (Student $student) use ($parser, &$totals): void {
                    $parts = $parser->parse($student->address);
                    $totals[$parts['address_needs_review'] ? 'revisar' : 'separados']++;

                    if ($this->option('dry-run')) {
                        $this->line(($parts['address_needs_review'] ? '⚠ ' : '✓ ').$student->address);
                        $this->line('   '.json_encode(array_intersect_key($parts, array_flip(['street', 'exterior_number', 'interior_number', 'neighborhood', 'municipality_id', 'postal_code'])), JSON_UNESCAPED_UNICODE));

                        return;
                    }

                    $student->forceFill($parts)->saveQuietly();
                });
        }

        $this->info("Separados: {$totals['separados']} · Para revisar: {$totals['revisar']}".($this->option('dry-run') ? ' (sin guardar)' : ''));

        return self::SUCCESS;
    }
}
