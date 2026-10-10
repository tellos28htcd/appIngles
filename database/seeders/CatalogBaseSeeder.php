<?php

namespace Database\Seeders;

use App\Actions\Catalogs\CopyBaseCatalogs;
use App\Enums\ChargeConceptType;
use App\Enums\LessonType;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\ChargeConcept;
use App\Models\Classroom;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\PaymentMethod;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo base (Información general.xlsx, pestaña Catálogos). Solo crea lo
 * que falta: lo editado desde Plataforma → Catálogos base se respeta.
 * Al final copia la base a las escuelas que aún no tienen catálogos.
 */
class CatalogBaseSeeder extends Seeder
{
    private const SHIFTS = ['Matutino', 'Vespertino', 'Sabatino'];

    /** Bloques de 1 h de 07:00 a 21:00; antes de las 14:00 = Matutino. */
    private const FIRST_HOUR = 7;

    private const LAST_HOUR = 21;

    private const AFTERNOON_FROM = 14;

    private const BOOKS = [1 => 'Beginners', 2 => 'Intermediate', 3 => 'Advanced'];

    /** Cada libro: [número de actividad, nombre, tipo] en el orden del Excel. */
    private const LESSONS = [
        [1, 'Lesson 1', LessonType::Lesson], [2, 'Lesson 2', LessonType::Lesson], [3, 'Lesson 3', LessonType::Lesson],
        [4, 'Lesson 4', LessonType::Lesson], [5, 'Lesson 5', LessonType::Lesson], [6, 'Lesson 6', LessonType::Lesson],
        [7, 'Lesson 7', LessonType::Lesson],
        [15, 'Check Point C1', LessonType::CheckPoint], [16, 'Verb in context 1', LessonType::VerbInContext],
        [8, 'Lesson 8', LessonType::Lesson], [9, 'Lesson 9', LessonType::Lesson], [10, 'Lesson 10', LessonType::Lesson],
        [11, 'Lesson 11', LessonType::Lesson], [12, 'Lesson 12', LessonType::Lesson], [13, 'Lesson 13', LessonType::Lesson],
        [14, 'Lesson 14', LessonType::Lesson],
        [17, 'Check Point C2', LessonType::CheckPoint], [18, 'Verb in context 2', LessonType::VerbInContext],
    ];

    private const ACTIVITIES = [
        [1, 'BA', 'Book Answered'],
        [2, '1P', 'Primera Pronunciation'],
        [3, 'M', 'Mentoría'],
        [4, 'WS', 'Worksheet'],
        [5, '2P', 'Segunda Pronunciation'],
        [6, 'WP', 'Written performance'],
        [7, 'SP', 'Spoken demonstration'],
        [8, 'PR', 'Presentación'],
    ];

    /** Club => horas por nivel 1, 2, 3 (null = no se ofrece en ese nivel). */
    private const CLUBS = [
        'Conversation Club' => [10, 12, 15],
        'Movie Club' => [5, 8, null],
        'Soundbooth Club' => [5, 7, 9],
        'Field Trip Club' => [2, 3, 4],
        'Book Club' => [null, 2, 3],
        'Ted Talks Club' => [null, null, 10],
    ];

    /** Método => ¿pide referencia (folio, autorización, número de cheque)? */
    private const PAYMENT_METHODS = [
        'Efectivo' => false,
        'Transferencia' => true,
        'Tarjeta de crédito' => true,
        'Tarjeta de débito' => true,
        'Cheque' => true,
    ];

    private const CHARGE_CONCEPTS = [
        'Inscripción' => ChargeConceptType::Enrollment,
        'Colegiatura mensual' => ChargeConceptType::Tuition,
        'Libro' => ChargeConceptType::Material,
        'Convenio de extensión' => ChargeConceptType::Extension,
        'Recargo por pago tardío' => ChargeConceptType::LateFee,
    ];

    public function run(): void
    {
        DB::transaction(fn () => AuditLog::muted(function (): void {
            $this->seedShiftsAndSlots();
            $books = $this->seedBooksAndLessons();
            $this->seedActivities();
            $this->seedClubs($books);
            $this->seedSchoolSettings();
        }));

        $copy = app(CopyBaseCatalogs::class);

        School::query()
            ->whereDoesntHave('shifts')
            ->whereDoesntHave('books')
            ->each(fn (School $school) => $copy->handle($school));

        // Catálogos de Configuración: se copian una sola vez a cada escuela que aún no
        // tiene nada vinculado a la base (lo que la escuela elimine después no regresa).
        $settings = array_intersect_key(CopyBaseCatalogs::CATALOGS, array_flip(['classrooms', 'payment_methods', 'charge_concepts', 'holidays']));

        School::query()->each(function (School $school) use ($copy, $settings): void {
            $only = [];

            foreach ($settings as $catalog => $model) {
                $linked = $model::withoutGlobalScope('school')->where('school_id', $school->id)->whereNotNull('base_id')->exists();

                if (! $linked) {
                    $only[$catalog] = CopyBaseCatalogs::pending($school, $model)->modelKeys();
                }
            }

            if (array_filter($only) !== []) {
                $copy->handle($school, $only);
            }
        });
    }

    /** Salones 1–9, métodos de pago y conceptos de cobro de la base (sin montos: el precio es de cada escuela). */
    private function seedSchoolSettings(): void
    {
        foreach (range(1, 9) as $number) {
            Classroom::ofCatalog(null)->firstOrCreate(['school_id' => null, 'name' => (string) $number], [
                'capacity' => 5,
                'description' => 'Salón',
                'is_active' => true,
            ]);
        }

        foreach (self::PAYMENT_METHODS as $name => $requiresReference) {
            PaymentMethod::ofCatalog(null)->firstOrCreate(['school_id' => null, 'name' => $name], [
                'requires_reference' => $requiresReference,
                'is_active' => true,
            ]);
        }

        foreach (self::CHARGE_CONCEPTS as $name => $type) {
            ChargeConcept::ofCatalog(null)->firstOrCreate(['school_id' => null, 'name' => $name], [
                'type' => $type,
                'is_active' => true,
            ]);
        }
    }

    private function seedShiftsAndSlots(): void
    {
        $shifts = [];
        foreach (self::SHIFTS as $order => $name) {
            $shifts[$name] = Shift::ofCatalog(null)->firstOrCreate(
                ['school_id' => null, 'name' => $name],
                ['sort_order' => ($order + 1) * 10, 'is_active' => true],
            );
        }

        for ($hour = self::FIRST_HOUR, $number = 1; $hour < self::LAST_HOUR; $hour++, $number++) {
            ScheduleSlot::ofCatalog(null)->firstOrCreate(['school_id' => null, 'number' => $number], [
                'shift_id' => $shifts[$hour < self::AFTERNOON_FROM ? 'Matutino' : 'Vespertino']->id,
                'starts_at' => sprintf('%02d:00:00', $hour),
                'ends_at' => sprintf('%02d:00:00', $hour + 1),
                'is_active' => true,
            ]);
        }
    }

    /** @return array<int, Book> nivel => libro */
    private function seedBooksAndLessons(): array
    {
        $books = [];
        foreach (self::BOOKS as $level => $name) {
            $book = Book::ofCatalog(null)->firstOrCreate(['school_id' => null, 'level' => $level], ['name' => $name, 'is_active' => true]);
            $books[$level] = $book;

            foreach (self::LESSONS as $position => [$number, $lessonName, $type]) {
                Lesson::ofCatalog(null)->firstOrCreate(['school_id' => null, 'book_id' => $book->id, 'number' => $number], [
                    'name' => $lessonName,
                    'type' => $type,
                    'sort_order' => ($position + 1) * 10,
                    'is_active' => true,
                ]);
            }
        }

        return $books;
    }

    private function seedActivities(): void
    {
        foreach (self::ACTIVITIES as [$number, $code, $description]) {
            Activity::ofCatalog(null)->firstOrCreate(['school_id' => null, 'code' => $code], [
                'number' => $number,
                'description' => $description,
                'is_active' => true,
            ]);
        }
    }

    /** @param array<int, Book> $books */
    private function seedClubs(array $books): void
    {
        $order = 0;
        foreach (self::CLUBS as $name => $hoursByLevel) {
            $club = Club::ofCatalog(null)->firstOrCreate(['school_id' => null, 'name' => $name], [
                'sort_order' => $order += 10,
                'is_active' => true,
            ]);

            if (! $club->wasRecentlyCreated) {
                continue;
            }

            $hours = [];
            foreach ($hoursByLevel as $index => $value) {
                if ($value !== null) {
                    $hours[$books[$index + 1]->id] = ['hours' => $value];
                }
            }

            $club->books()->sync($hours);
        }
    }
}
