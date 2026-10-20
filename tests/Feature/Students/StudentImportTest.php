<?php

namespace Tests\Feature\Students;

use App\Actions\Students\ImportStudents;
use App\Enums\StudentStatus;
use App\Enums\StudentTermMovementType;
use App\Jobs\DownloadStudentPhoto;
use App\Livewire\Students\StudentImport;
use App\Models\Guardian;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\SalesAdvisor;
use App\Models\School;
use App\Models\State;
use App\Models\Student;
use App\Models\StudentSurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Support\StudentTemplate;
use Database\Seeders\CatalogBaseSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StudentCatalogBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Municipality $queretaro;

    private Municipality $corregidora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class, CatalogBaseSeeder::class]);
        $state = State::create(['code' => '22', 'name' => 'Querétaro']);
        $this->queretaro = Municipality::create(['state_id' => $state->id, 'code' => '014', 'name' => 'Querétaro']);
        $this->corregidora = Municipality::create(['state_id' => $state->id, 'code' => '006', 'name' => 'Corregidora']);
        $this->school = School::factory()->create(['enrollment_prefix' => 'QRON', 'state_id' => $state->id]);
        $this->seed(StudentCatalogBaseSeeder::class);
        $this->actingAs(User::factory()->create([
            'role_id' => Role::where('slug', Role::SCHOOL_ADMIN)->value('id'),
            'school_id' => $this->school->id,
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $students  encabezado => valor
     * @param  list<array<string, mixed>>  $advisors
     */
    private function workbook(array $students, array $advisors = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'alumnos').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(StudentTemplate::STUDENTS_SHEET);

        $headers = array_values(array_unique(array_merge(...array_map('array_keys', $students))));
        $writer->addRow(Row::fromValues($headers));
        foreach ($students as $student) {
            $writer->addRow(Row::fromValues(array_map(fn (string $header) => $student[$header] ?? '', $headers)));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName(StudentTemplate::ADVISORS_SHEET);
        $writer->addRow(Row::fromValues(['Nombre', 'Puesto', 'Fecha de ingreso', 'Fecha de baja']));
        foreach ($advisors as $advisor) {
            $writer->addRow(Row::fromValues(array_values($advisor)));
        }

        $writer->close();

        return $path;
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $question = SurveyQuestion::ofCatalog($this->school->id)->where('question', '¿Quién cubre el pago de tu curso?')->sole();

        return [
            [
                'Matrícula' => 'QRON23040001', 'Nombre(s)' => 'María Isela', 'Primer apellido' => 'Amado', 'Segundo apellido' => 'Reyes',
                'Celular' => 4461180095, 'Teléfono fijo' => '4422150000', 'Correo electrónico' => 'isela@appingles.com', 'Fecha de nacimiento' => 30805,
                'Domicilio' => 'AV. DE LA LUZ NO. 1500, INT. 15, COL. PUESTA DEL SOL, QRO. C.P. 76100',
                'Fecha de inicio del curso' => '03/04/2023', 'Días de ampliación de plazo' => 30, 'Tipo de material (F/C)' => 'f',
                'Tipo de alumno (clave)' => 'ALUMN', 'Origen de la información' => 'info pres-adm', 'Estatus' => 'Activo',
                'Foto (enlace)' => 'https://drive.google.com/uc?id=abc123', 'No. de contrato' => '2',
                'Asesor de ventas' => 'GIANCARLO HERNANDEZ', 'Plan (clave)' => 'F', 'Fecha de la ficha de inscripción' => '01/04/2023',
                'Fecha de baja' => 73415, 'TOEFL' => 'Sí', 'Aplica material' => 'si', 'Libro de inicio (nivel)' => 1, 'Lección de inicio (número)' => 1,
                'Tutor: nombre' => 'Rosa Reyes', 'Tutor: celular' => '4420000000', 'Tutor: información que recibe' => 'FA, PAGOS',
                $question->question => 'Mi pápa',
            ],
            [
                'Nombre(s)' => 'Juan', 'Primer apellido' => 'Pérez', 'Estatus' => 'Baja', 'Motivo del estatus' => 'Devolvió el material',
                'Fecha de baja' => '15/05/2025', 'Tutor: nombre' => 'ROSA REYES', 'Tutor: celular' => '4420000000',
                'Calle' => 'Pino', 'Núm. ext.' => '12', 'Colonia' => 'Centro', 'Ciudad / municipio' => 'corregidora', 'C.P.' => '76900',
            ],
            ['Nombre(s)' => 'Sin apellido', 'Correo electrónico' => 'isela@appingles.com'],
        ];
    }

    public function test_preview_reports_errors_and_warnings_without_saving(): void
    {
        $preview = app(ImportStudents::class)->preview($this->school, $this->workbook($this->rows()));

        $this->assertCount(3, $preview['rows']);
        $this->assertSame([], $preview['rows'][0]['errors']);
        $this->assertNotEmpty(collect($preview['rows'][0]['warnings'])->filter(fn ($warning) => str_contains($warning, 'GIANCARLO')));
        $this->assertSame([], $preview['rows'][1]['errors']);
        $this->assertCount(1, $preview['rows'][2]['errors']); // sin apellido
        $this->assertNotEmpty(collect($preview['rows'][2]['warnings'])->filter(fn ($warning) => str_contains($warning, 'fila 2')));
        $this->assertSame(0, Student::count());
    }

    public function test_import_fills_every_related_table_without_sending_mail(): void
    {
        Notification::fake();
        Queue::fake();

        $result = app(ImportStudents::class)->import($this->school, $this->workbook($this->rows(), [
            ['GIANCARLO HERNANDEZ', 'Gerente', '01/01/2023', ''],
        ]));

        $this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 1, 'advisors' => 1, 'photos' => 1, 'failed' => []], $result);

        $isela = Student::with(['enrollments.plan', 'enrollments.salesAdvisor.position', 'informationSource', 'guardians', 'user'])->where('enrollment_number', 'QRON23040001')->sole();
        $this->assertSame('MARÍA ISELA', $isela->first_name);
        $this->assertSame('1984-05-03', $isela->birth_date->toDateString());
        $this->assertSame('4461180095', $isela->mobile_phone);
        $this->assertSame('Info Pres-adm', $isela->informationSource->name);
        $this->assertNotNull($isela->user);
        $enrollment = $isela->enrollments->sole();
        $this->assertSame('F', $enrollment->plan->code);
        $this->assertSame(10, $enrollment->installments);
        $this->assertNull($isela->withdrawn_on);
        $this->assertSame('2023-04-03', $isela->course_starts_on->toDateString());
        $this->assertSame('2024-05-02', $isela->courseEndsOn()->toDateString()); // 365 + 30 días de ampliación
        $this->assertSame('4422150000', $isela->landline_phone);
        $this->assertSame(['AV. DE LA LUZ', '1500', '15', 'PUESTA DEL SOL', '76100', $this->queretaro->id, false],
            [$isela->street, $isela->exterior_number, $isela->interior_number, $isela->neighborhood, $isela->postal_code, $isela->municipality_id, $isela->address_needs_review]);
        $this->assertTrue($enrollment->includes_toefl);
        $this->assertSame([[StudentTermMovementType::Extension, 30]], $isela->termMovements->map(fn ($movement) => [$movement->type, $movement->days])->all());
        $this->assertSame([true, 'F'], [$enrollment->material_assigned, $enrollment->material_type]);
        $this->assertSame('Gerente', $enrollment->salesAdvisor->position->name);
        $this->assertSame(['academic_record', 'payments'], $isela->guardians->sole()->receives);
        $this->assertSame('Mi papá', StudentSurveyAnswer::where('student_id', $isela->id)->value('answer'));

        $juan = Student::with('statusChanges')->where('first_name', 'JUAN')->sole();
        $this->assertSame(StudentStatus::Withdrawn, $juan->status);
        $this->assertNull($juan->user_id);
        $this->assertSame('Devolvió el material', $juan->statusChanges->sole()->reason);
        $this->assertSame('2025-05-15', $juan->statusChanges->sole()->changed_on->toDateString());
        $this->assertSame('2025-05-15', $juan->withdrawn_on->toDateString());
        $this->assertSame(['PINO', '12', 'CENTRO', '76900', $this->corregidora->id, $this->school->state_id],
            [$juan->street, $juan->exterior_number, $juan->neighborhood, $juan->postal_code, $juan->municipality_id, $juan->state_id]);
        $this->assertMatchesRegularExpression('/^QRON\d{4}0002$/', $juan->enrollment_number);

        // El mismo tutor (nombre y celular) se registra una sola vez para ambos alumnos.
        $this->assertSame(1, Guardian::count());
        $this->assertSame(2, $this->school->fresh()->last_enrollment_sequence);

        Notification::assertNothingSent();
        Queue::assertPushed(DownloadStudentPhoto::class, 1);
    }

    public function test_importing_the_same_file_again_updates_instead_of_duplicating(): void
    {
        Queue::fake();
        $rows = array_slice($this->rows(), 0, 1);
        app(ImportStudents::class)->import($this->school, $this->workbook($rows));

        $rows[0]['Celular'] = '4429999999';
        $result = app(ImportStudents::class)->import($this->school, $this->workbook($rows));

        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, Student::count());
        $this->assertSame('4429999999', Student::sole()->mobile_phone);
        $this->assertSame(1, Student::sole()->enrollments()->count());
    }

    public function test_a_bad_email_does_not_block_the_student(): void
    {
        Queue::fake();
        $result = app(ImportStudents::class)->import($this->school, $this->workbook([
            ['Nombre(s)' => 'Raúl', 'Primer apellido' => 'Peña', 'Correo electrónico' => 'raulpeña soto@appingles.com'],
        ]));

        $this->assertSame(1, $result['created']);
        $this->assertNull(Student::sole()->email);
        $this->assertNull(Student::sole()->user_id);
    }

    public function test_advisors_of_the_advisors_sheet_are_not_reported_as_missing(): void
    {
        $preview = app(ImportStudents::class)->preview($this->school, $this->workbook(
            [['Nombre(s)' => 'Ana', 'Primer apellido' => 'Ruiz', 'Asesor de ventas' => 'Nuevo Asesor']],
            [['NUEVO ASESOR', 'Gerente', '', '']],
        ));

        $this->assertSame([], collect($preview['rows'][0]['warnings'])->filter(fn ($warning) => str_contains($warning, 'asesor'))->all());
    }

    public function test_a_number_from_another_school_is_rejected(): void
    {
        $other = School::factory()->create();
        Student::factory()->create(['school_id' => $other->id, 'enrollment_number' => 'OTRA26010001']);

        $preview = app(ImportStudents::class)->preview($this->school, $this->workbook([
            ['Matrícula' => 'OTRA26010001', 'Nombre(s)' => 'Ana', 'Primer apellido' => 'Ruiz'],
        ]));

        $this->assertNotEmpty($preview['rows'][0]['errors']);
    }

    public function test_import_screen_reviews_then_imports(): void
    {
        Queue::fake();
        $file = UploadedFile::fake()->createWithContent('alumnos.xlsx', file_get_contents($this->workbook(array_slice($this->rows(), 0, 2))));

        Livewire::test(StudentImport::class)
            ->set('file', $file)
            ->call('review')
            ->assertSet('preview.create', 2)
            ->assertSet('preview.with_errors', 0)
            ->call('import')
            ->assertSet('result.created', 2);

        $this->assertSame(2, Student::count());
    }

    public function test_template_lists_the_school_catalogs_and_survey(): void
    {
        SalesAdvisor::create(['school_id' => $this->school->id, 'name' => 'ASESOR PRUEBA']);

        $response = $this->get(route('students.template'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'plantilla').'.xlsx';
        copy($path, $copy);

        $reader = new Reader;
        $reader->open($copy);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        $this->assertSame(['Alumnos', 'Asesores', 'Valores', 'Instrucciones'], array_keys($sheets));
        $this->assertContains('Nombre(s)', $sheets['Alumnos'][0]);
        $this->assertContains('¿Quién cubre el pago de tu curso?', $sheets['Alumnos'][0]);
        $this->assertContains('ASESOR PRUEBA', array_column($sheets['Valores'], 3));
    }

    public function test_only_managers_import_or_download_the_template(): void
    {
        $this->actingAs(User::factory()->create([
            'role_id' => Role::where('slug', Role::RECEPTION)->value('id'),
            'school_id' => $this->school->id,
        ]));

        $this->get(route('students.template'))->assertForbidden();
        $this->get(route('students.import'))->assertForbidden();
    }

    public function test_photo_links_are_restricted_to_public_https(): void
    {
        $this->assertSame('https://drive.google.com/uc?export=download&id=abc_1', DownloadStudentPhoto::directDownloadUrl('https://drive.google.com/file/d/abc_1/view'));
        $this->assertFalse(DownloadStudentPhoto::isSafeUrl('http://example.com/foto.jpg'));
        $this->assertFalse(DownloadStudentPhoto::isSafeUrl('https://127.0.0.1/foto.jpg'));
        $this->assertFalse(DownloadStudentPhoto::isSafeUrl('https://192.168.1.10/foto.jpg'));
        $this->assertFalse(DownloadStudentPhoto::isSafeUrl('https://user@8.8.8.8/foto.jpg'));
    }
}
