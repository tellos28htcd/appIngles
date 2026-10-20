<?php

namespace Database\Seeders;

use App\Actions\Catalogs\CopyBaseCatalogs;
use App\Enums\SurveyQuestionType;
use App\Models\AdvisorPosition;
use App\Models\AuditLog;
use App\Models\InformationSource;
use App\Models\School;
use App\Models\StudentPlan;
use App\Models\StudentType;
use App\Models\SurveyQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos base del módulo Alumnos (IQN4 AA Concentrado Alumnos, pestaña
 * Catalogos y encuesta). Solo crea lo que falta: lo editado en Plataforma se
 * respeta. Al final copia cada catálogo una vez a las escuelas que aún no lo tienen.
 */
class StudentCatalogBaseSeeder extends Seeder
{
    /** clave => [nombre, mensualidades]; el Plan A es de contado (0 mensualidades). */
    private const PLANS = [
        'A' => ['Plan A', 0],
        'B' => ['Plan B', 1],
        'C' => ['Plan C', 3],
        'C4' => ['Plan C', 4],
        'C5' => ['Plan C', 5],
        'D' => ['Plan D', 6],
        'D7' => ['Plan D', 7],
        'E' => ['Plan E', 8],
        'F9' => ['Plan F', 9],
        'F' => ['Plan F', 10],
        'F11' => ['Plan F', 11],
        'X' => ['Plan X', 12],
    ];

    /** [nombre, origen, nombre corto] */
    private const SOURCES = [
        ['Facebook-Adm', 'Enlace Digital', 'Adm'],
        ['Instagram-Adm', 'Enlace Digital', 'Adm'],
        ['Ticktoc-Adm', 'Enlace Digital', 'Adm'],
        ['Whatsapp-Adm', 'Enlace Digital', 'Adm'],
        ['Google Ads-Adm', 'Enlace Digital', 'Adm'],
        ['Facebook-Inf Gan', 'Enlace Digital', 'Inf'],
        ['Instagram-Inf Gan', 'Enlace Digital', 'Inf'],
        ['Ticktoc-Inf Gan', 'Enlace Digital', 'Inf'],
        ['Whatsapp-Inf Gan', 'Enlace Digital', 'Inf'],
        ['Google Ads-Gan', 'Enlace Digital', 'Inf'],
        ['Info Pres-adm', 'Administrativa', 'Adm'],
        ['Remarquetink-adm', 'Administrativa', 'Adm'],
        ['Info Pres-Inf Gan', 'Información ganada', 'Inf'],
        ['Personal', 'Personal', 'Per'],
        ['Propia', 'Personal', 'Per'],
        ['Volante', 'Administrativa', 'Adm'],
        ['Enlace Digital - Facebook', 'Enlace Digital', 'ED'],
    ];

    private const TYPES = [
        'ALUMN' => 'Alumno nuevo',
        'TRASP' => 'Traspaso',
        'COLAB' => 'Colaborador',
        'AL_RE' => 'Alumno reasignado',
        'CACAD' => 'Concepto académico-administrativo',
        'TDRFO' => 'Alumno foráneo con Tarjeta Dorada',
    ];

    /** Puesto => orden de jerarquía */
    private const POSITIONS = [
        'Franquiciatario(a)' => 1,
        'Gerente' => 2,
        'Supervisor(a)' => 3,
        'Coordinador(a)' => 4,
        'Representante' => 5,
        'Intercambio' => 6,
    ];

    /** [sección, pregunta, opciones (null = texto libre)] */
    private const QUESTIONS = [
        ['Contexto académico', '¿Estás estudiando actualmente?', ['No', 'Sí, preparatoria', 'Sí, universidad', 'Sí, posgrado', 'Sí, estudio y trabajo']],
        ['Contexto académico', '¿Dónde estás estudiando actualmente?', null],
        ['Contexto académico', '¿Cuál es tu periodo vacacional?', ['De verano (Jul – Ago)', 'De invierno (Dic – Ene)', 'De Semana Santa (Mar – Abr)', 'Aún no tengo fechas definidas', 'No aplica / no estoy estudiando']],
        ['Contexto académico', '¿Cuál es tu ocupación?', null],
        ['Contexto académico', '¿Cuál es tu principal objetivo al estudiar inglés?', ['Desarrollo personal', 'Mejorar en mi trabajo actual', 'Obtener un nuevo empleo', 'Presentar examen de certificación', 'Requisito de la escuela', 'Viajar', 'Otro']],
        ['Situación laboral', '¿Estás trabajando actualmente?', ['Sí, de tiempo completo', 'Sí, medio tiempo', 'Sí, por proyecto o freelance', 'No trabajo actualmente']],
        ['Situación laboral', '¿Qué tipo de horario tienes en tu trabajo?', ['Turno fijo matutino', 'Turno fijo vespertino', 'Turno nocturno', 'Rolo turnos', 'Horario flexible', 'Sin horario definido']],
        ['Situación laboral', '¿En qué empresa trabajas?', null],
        ['Situación laboral', '¿Dónde se ubica?', null],
        ['Disponibilidad de tiempo', '¿Qué días puedes tomar clase con mayor facilidad?', ['Lunes a viernes', 'Solo sábados', 'Lunes a sábado', 'Depende del turno', 'Actualmente no tengo días disponibles']],
        ['Disponibilidad de tiempo', '¿En qué momento del día te resulta más fácil asistir a clase?', ['Mañana', 'Mediodía', 'Tarde', 'Noche', 'Varía según el día']],
        ['Ubicación y traslado', '¿Cuál es tu principal medio de transporte?', ['Transporte público', 'Auto propio', 'Moto o bicicleta', 'Caminando', 'Me trasladan', 'Otro']],
        ['Ubicación y traslado', '¿Cuánto tiempo haces en llegar a la escuela?', null],
        ['Situación personal', '¿Cuál es tu estado civil?', ['Soltero(a)', 'Casado(a)', 'Unión libre', 'Otro']],
        ['Situación personal', '¿Con quién vives actualmente?', ['Con padres o familia', 'Solo(a)', 'Con pareja', 'Con roomies', 'Otro']],
        ['Situación personal', '¿Tienes responsabilidades familiares que interfieran con tus clases?', ['No, por ahora no', 'Sí, trabajo', 'Sí, escuela u otros cursos', 'Sí, cuidados familiares']],
        ['Situación personal', '¿Eres originario de esta ciudad?', ['Sí, soy local', 'No, soy de otro estado']],
        ['Situación personal', '¿De qué estado eres?', null],
        ['Economía', '¿Quién cubre el pago de tu curso?', ['Yo', 'Mi papá', 'Mi mamá', 'Beca', 'Otro']],
        ['Faltas o inasistencia', '¿Qué ha sido lo que más te ha impedido asistir a clases/prácticas?', ['Trabajo o cambios de turno', 'Escuela u otras actividades', 'Salud', 'Temas económicos', 'Otro motivo']],
        ['Faltas o inasistencia', '¿Qué podrías necesitar para retomar tu curso con más regularidad?', ['Flexibilidad de horario', 'Recordatorios o seguimiento', 'Sentirme más motivado', 'Resolver temas económicos', 'Otro']],
        ['Comunicación', '¿Cuál es tu medio preferido de contacto?', ['WhatsApp', 'Llamada telefónica', 'Correo electrónico']],
        ['Comunicación', '¿En qué horario prefieres que te contactemos?', ['Mañana', 'Mediodía', 'Tarde', 'Noche', 'Cualquier horario está bien']],
        ['Otros', '¿Tienes alguna condición médica o personal que debamos tomar en cuenta?', ['No', 'Sí']],
        ['Otros', '¿Cómo te gustaría que te apoyemos desde el área de atención al alumno?', ['Recordándome mis clases o pagos', 'Acompañándome si falto o me atraso', 'Apoyándome con opciones si no puedo asistir', 'Ayudándome a organizar mi calendario', 'Otro']],
    ];

    public function run(): void
    {
        DB::transaction(fn () => AuditLog::muted(function (): void {
            foreach (self::PLANS as $code => [$name, $installments]) {
                StudentPlan::ofCatalog(null)->firstOrCreate(['school_id' => null, 'code' => $code], [
                    'name' => $name, 'installments' => $installments, 'is_active' => true,
                ]);
            }

            foreach (self::SOURCES as [$name, $channel, $shortName]) {
                InformationSource::ofCatalog(null)->firstOrCreate(['school_id' => null, 'name' => $name], [
                    'channel' => $channel, 'short_name' => $shortName, 'is_active' => true,
                ]);
            }

            foreach (self::TYPES as $code => $name) {
                StudentType::ofCatalog(null)->firstOrCreate(['school_id' => null, 'code' => $code], ['name' => $name, 'is_active' => true]);
            }

            foreach (self::POSITIONS as $name => $rank) {
                AdvisorPosition::ofCatalog(null)->firstOrCreate(['school_id' => null, 'name' => $name], ['rank' => $rank, 'is_active' => true]);
            }

            foreach (self::QUESTIONS as $index => [$section, $question, $options]) {
                SurveyQuestion::ofCatalog(null)->firstOrCreate(['school_id' => null, 'question' => $question], [
                    'section' => $section,
                    'type' => $options === null ? SurveyQuestionType::Text : SurveyQuestionType::Choice,
                    'options' => $options,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ]);
            }
        }));

        $this->copyOnceToSchools();
    }

    /** Cada catálogo se copia una sola vez a la escuela que aún no tiene nada vinculado a la base. */
    private function copyOnceToSchools(): void
    {
        $copy = app(CopyBaseCatalogs::class);
        $catalogs = array_intersect_key(CopyBaseCatalogs::CATALOGS, array_flip([
            'student_plans', 'information_sources', 'student_types', 'advisor_positions', 'survey_questions',
        ]));

        School::query()->each(function (School $school) use ($copy, $catalogs): void {
            $only = [];

            foreach ($catalogs as $catalog => $model) {
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
}
