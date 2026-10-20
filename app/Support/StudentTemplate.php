<?php

namespace App\Support;

use App\Models\SurveyQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Columnas de la plantilla de importación de alumnos (hoja "Alumnos"). Las
 * preguntas activas de la encuesta de la escuela se agregan al final con el
 * texto de la pregunta como encabezado. La hoja "Asesores" da de alta a los
 * asesores de ventas (con su puesto y fechas).
 */
final class StudentTemplate
{
    public const STUDENTS_SHEET = 'Alumnos';

    public const ADVISORS_SHEET = 'Asesores';

    public const VALUES_SHEET = 'Valores';

    public const INSTRUCTIONS_SHEET = 'Instrucciones';

    /**
     * clave => [encabezado, tipo]. Tipos: text, date, bool, number, list:<valores>.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const COLUMNS = [
        'enrollment_number' => ['Matrícula', 'text'],
        'first_name' => ['Nombre(s)', 'text'],
        'last_name' => ['Primer apellido', 'text'],
        'second_last_name' => ['Segundo apellido', 'text'],
        'mobile_phone' => ['Celular', 'text'],
        'landline_phone' => ['Teléfono fijo', 'text'],
        'street' => ['Calle', 'text'],
        'exterior_number' => ['Núm. ext.', 'text'],
        'interior_number' => ['Núm. int.', 'text'],
        'neighborhood' => ['Colonia', 'text'],
        'municipality' => ['Ciudad / municipio', 'text'],
        'state' => ['Estado', 'text'],
        'postal_code' => ['C.P.', 'text'],
        'address' => ['Domicilio (en una línea)', 'text'],
        'email' => ['Correo electrónico', 'text'],
        'birth_date' => ['Fecha de nacimiento', 'date'],
        'student_type' => ['Tipo de alumno (clave)', 'list:types'],
        'information_source' => ['Origen de la información', 'list:sources'],
        'advertising_consent' => ['Acepta aparecer en publicidad', 'bool'],
        'learns_visual' => ['Aprendizaje visual', 'bool'],
        'learns_auditory' => ['Aprendizaje auditivo', 'bool'],
        'learns_kinesthetic' => ['Aprendizaje kinestésico', 'bool'],
        'status' => ['Estatus', 'list:statuses'],
        'status_date' => ['Fecha del estatus', 'date'],
        'status_reason' => ['Motivo del estatus', 'text'],
        'photo_url' => ['Foto (enlace)', 'text'],
        'notes' => ['Observaciones', 'text'],
        'contract_number' => ['No. de contrato', 'text'],
        'sales_advisor' => ['Asesor de ventas', 'list:advisors'],
        'plan' => ['Plan (clave)', 'list:plans'],
        'installments' => ['Mensualidades', 'number'],
        'registered_on' => ['Fecha de la ficha de inscripción', 'date'],
        'course_starts_on' => ['Fecha de inicio del curso', 'date'],
        'withdrawn_on' => ['Fecha de baja', 'date'],
        'includes_term_extension' => ['Ampliación de plazo', 'bool'],
        'extension_days' => ['Días de ampliación de plazo', 'number'],
        'includes_toefl' => ['Preparación TOEFL', 'bool'],
        'includes_gold_card' => ['Tarjeta dorada', 'bool'],
        'material_serial' => ['Número de serie del material', 'text'],
        'material_type' => ['Tipo de material (F/C)', 'list:material_types'],
        'material_assigned' => ['Aplica material', 'bool'],
        'commission_applies' => ['Aplica comisión', 'bool'],
        'start_book' => ['Libro de inicio (nivel)', 'number'],
        'start_lesson' => ['Lección de inicio (número)', 'number'],
        'start_activity' => ['Actividad de inicio (número)', 'number'],
        'reenrolled_on' => ['Fecha de reinscripción', 'date'],
        'graduated_on' => ['Fecha de graduación', 'date'],
        'absence_from' => ['Ausencia desde', 'date'],
        'absence_to' => ['Ausencia hasta', 'date'],
        'guardian_name' => ['Tutor: nombre', 'text'],
        'guardian_phone' => ['Tutor: celular', 'text'],
        'guardian_email' => ['Tutor: correo', 'text'],
        'guardian_receives' => ['Tutor: información que recibe', 'text'],
    ];

    /** @var array<string, array{0: string, 1: string}> */
    public const ADVISOR_COLUMNS = [
        'name' => ['Nombre', 'text'],
        'position' => ['Puesto', 'list:positions'],
        'hired_on' => ['Fecha de ingreso', 'date'],
        'left_on' => ['Fecha de baja', 'date'],
    ];

    public const REQUIRED = ['first_name', 'last_name'];

    /** Encabezados de versiones anteriores de la plantilla que se siguen aceptando. */
    private const ALIASES = ['toefl' => 'includes_toefl', 'domicilio' => 'address'];

    /** Encabezados normalizados (sin acentos ni mayúsculas) para reconocer las columnas aunque cambien ligeramente. */
    public static function normalize(string $text): string
    {
        return Str::of(Str::ascii($text))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }

    /**
     * Encabezado normalizado => clave de columna (o "q:<id>" para preguntas de la encuesta).
     *
     * @param  Collection<int, SurveyQuestion>  $questions
     * @return array<string, string>
     */
    public static function headerMap(Collection $questions): array
    {
        $map = collect(self::COLUMNS)->mapWithKeys(fn (array $column, string $key) => [self::normalize($column[0]) => $key])->all() + self::ALIASES;

        foreach ($questions as $question) {
            $map[self::normalize($question->question)] = 'q:'.$question->id;
        }

        return $map;
    }

    /** @return array<string, string> encabezado normalizado => clave */
    public static function advisorHeaderMap(): array
    {
        return collect(self::ADVISOR_COLUMNS)->mapWithKeys(fn (array $column, string $key) => [self::normalize($column[0]) => $key])->all();
    }
}
