<?php

namespace App\Actions\Students;

use App\Enums\StudentStatus;
use App\Enums\StudentTermMovementType;
use App\Enums\SurveyQuestionType;
use App\Jobs\DownloadStudentPhoto;
use App\Models\Activity;
use App\Models\AdvisorPosition;
use App\Models\Book;
use App\Models\Guardian;
use App\Models\InformationSource;
use App\Models\Lesson;
use App\Models\SalesAdvisor;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAbsence;
use App\Models\StudentEnrollment;
use App\Models\StudentPlan;
use App\Models\StudentSurveyAnswer;
use App\Models\StudentTermMovement;
use App\Models\StudentType;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Support\AddressParser;
use App\Support\StudentTemplate;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Importa alumnos desde la plantilla .xlsx. Primero se revisa (preview) y
 * luego se importa solo lo que no tiene errores. Cada alumno se reconoce por
 * su matrícula: volver a subir el archivo actualiza, no duplica. Nunca envía
 * correos; las fotos con enlace se descargan en segundo plano.
 */
final class ImportStudents
{
    /** Máximo de filas por archivo. */
    public const MAX_ROWS = 5000;

    private School $school;

    /** @var Collection<int, SurveyQuestion> */
    private Collection $questions;

    /** @var array<string, array<string, int>> catálogo => valor normalizado => id */
    private array $lookups = [];

    private ?AddressParser $addressParser = null;

    public function __construct(
        private EnrollmentNumbers $numbers,
        private ChangeStudentStatus $changeStatus,
        private SyncStudentUser $syncUser,
    ) {}

    /**
     * Lee y revisa el archivo sin guardar nada.
     *
     * @return array{rows: list<array{line: int, name: string, number: ?string, action: string, errors: list<string>, warnings: list<string>}>, advisors: int, unknown_columns: list<string>, missing_columns: list<string>}
     */
    public function preview(School $school, string $path): array
    {
        $parsed = $this->parse($school, $path);
        $seenNumbers = [];
        $seenEmails = [];
        $rows = [];

        // Los asesores de la hoja Asesores se darán de alta: no se avisan como faltantes.
        foreach ($parsed['advisors'] as $advisor) {
            if (($name = $this->text($advisor, 'name')) !== null && $this->lookup('advisors', $name) === null) {
                $this->lookups['advisors'][StudentTemplate::normalize($name)] = 0;
            }
        }

        foreach ($parsed['students'] as $line => $data) {
            $rows[] = $this->check($line, $data, $seenNumbers, $seenEmails);
        }

        return [
            'rows' => $rows,
            'advisors' => count($parsed['advisors']),
            'unknown_columns' => $parsed['unknown_columns'],
            'missing_columns' => $parsed['missing_columns'],
        ];
    }

    /**
     * Importa las filas sin errores.
     *
     * @return array{created: int, updated: int, skipped: int, advisors: int, photos: int, failed: list<int>}
     */
    public function import(School $school, string $path): array
    {
        $parsed = $this->parse($school, $path);
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'advisors' => 0, 'photos' => 0, 'failed' => []];
        $seenNumbers = [];
        $seenEmails = [];

        $result['advisors'] = $this->importAdvisors($parsed['advisors']);

        foreach ($parsed['students'] as $line => $data) {
            $check = $this->check($line, $data, $seenNumbers, $seenEmails);

            if ($check['errors'] !== []) {
                $result['skipped']++;

                continue;
            }

            try {
                $student = DB::transaction(fn () => $this->importRow($data, $check['email']));
            } catch (Throwable $exception) {
                Log::error('No se pudo importar la fila de alumno.', ['school_id' => $school->id, 'line' => $line, 'error' => $exception->getMessage()]);
                $result['failed'][] = $line;

                continue;
            }

            $result[$check['action'] === 'update' ? 'updated' : 'created']++;

            if ($student->photo_path === null && $student->photo_source_url !== null) {
                DownloadStudentPhoto::dispatch($student->id)->afterCommit();
                $result['photos']++;
            }
        }

        return $result;
    }

    // ── Lectura ────────────────────────────────────────────────────

    /**
     * @return array{students: array<int, array<string, mixed>>, advisors: list<array<string, mixed>>, unknown_columns: list<string>, missing_columns: list<string>}
     */
    private function parse(School $school, string $path): array
    {
        $this->school = $school;
        $this->questions = SurveyQuestion::query()->ofCatalog($school->id)->orderBy('sort_order')->get()->keyBy('id');
        $this->lookups = [];

        // Se conservan las filas vacías para que el número de fila coincida con el de Excel.
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);

        $students = [];
        $advisors = [];
        $unknown = [];
        $missing = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $name = StudentTemplate::normalize($sheet->getName());
                $isStudents = $name === StudentTemplate::normalize(StudentTemplate::STUDENTS_SHEET);
                $isAdvisors = $name === StudentTemplate::normalize(StudentTemplate::ADVISORS_SHEET);

                if (! $isStudents && ! $isAdvisors) {
                    continue;
                }

                $map = $isStudents ? StudentTemplate::headerMap($this->questions) : StudentTemplate::advisorHeaderMap();
                $columns = null;

                foreach ($sheet->getRowIterator() as $line => $row) {
                    $cells = $row->toArray();

                    if ($columns === null) {
                        $columns = [];
                        foreach ($cells as $index => $header) {
                            $key = $map[StudentTemplate::normalize((string) $header)] ?? null;
                            $key === null
                                ? ($isStudents && trim((string) $header) !== '' ? $unknown[] = trim((string) $header) : null)
                                : $columns[$index] = $key;
                        }

                        if ($isStudents) {
                            $missing = array_values(array_diff(StudentTemplate::REQUIRED, $columns));
                        }

                        continue;
                    }

                    $data = [];
                    foreach ($columns as $index => $key) {
                        $value = $cells[$index] ?? null;
                        $data[$key] = is_string($value) ? trim($value) : $value;
                    }

                    if (collect($data)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                        continue;
                    }

                    if ($isStudents) {
                        $students[$line] = $data;
                    } else {
                        $advisors[] = $data;
                    }

                    if (count($students) > self::MAX_ROWS) {
                        break 2;
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return ['students' => $students, 'advisors' => $advisors, 'unknown_columns' => $unknown, 'missing_columns' => $missing];
    }

    // ── Revisión ───────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $seenNumbers
     * @param  array<string, int>  $seenEmails
     * @return array{line: int, name: string, number: ?string, action: string, email: ?string, errors: list<string>, warnings: list<string>}
     */
    private function check(int $line, array $data, array &$seenNumbers, array &$seenEmails): array
    {
        $errors = [];
        $warnings = [];
        $number = $this->text($data, 'enrollment_number');
        $number = $number === null ? null : Str::upper(preg_replace('/\s+/', '', $number));
        $existing = null;

        foreach (StudentTemplate::REQUIRED as $field) {
            if ($this->text($data, $field) === null) {
                $errors[] = __('students.import.errors.required', ['field' => StudentTemplate::COLUMNS[$field][0]]);
            }
        }

        if ($number !== null) {
            if (! preg_match('/^[A-Z0-9\-]{3,30}$/', $number)) {
                $errors[] = __('students.import.errors.enrollment_number', ['value' => $number]);
            } elseif (isset($seenNumbers[$number])) {
                $errors[] = __('students.import.errors.duplicate_number', ['line' => $seenNumbers[$number]]);
            } else {
                $seenNumbers[$number] = $line;
                $existing = Student::withoutGlobalScope('school')->where('enrollment_number', $number)->first();

                if ($existing !== null && $existing->school_id !== $this->school->id) {
                    $errors[] = __('students.import.errors.other_school', ['value' => $number]);
                }
            }
        }

        // Un correo con problema no impide importar al alumno: se registra sin correo (sin acceso) y se avisa.
        $email = $this->text($data, 'email');
        $email = $email === null ? null : Str::lower($email);
        $emailProblem = match (true) {
            $email === null => __('students.import.warnings.no_email'),
            filter_var($email, FILTER_VALIDATE_EMAIL) === false => __('students.import.warnings.email_invalid', ['value' => $email]),
            isset($seenEmails[$email]) => __('students.import.warnings.email_duplicate', ['value' => $email, 'line' => $seenEmails[$email]]),
            User::where('email', $email)->when($existing?->user_id, fn ($query, $userId) => $query->whereKeyNot($userId))->exists()
                || Student::withoutGlobalScope('school')->where('school_id', $this->school->id)->where('email', $email)
                    ->when($existing?->id, fn ($query, $id) => $query->whereKeyNot($id))->exists() => __('students.import.warnings.email_taken', ['value' => $email]),
            default => null,
        };

        if ($emailProblem !== null) {
            $warnings[] = $emailProblem;
            $email = null;
        } else {
            $seenEmails[$email] = $line;
        }

        foreach (['birth_date', 'status_date', 'registered_on', 'material_delivered_on', 'course_starts_on', 'withdrawn_on', 'reenrolled_on', 'graduated_on', 'absence_from', 'absence_to'] as $field) {
            if ($this->filled($data, $field) && $this->date($data, $field) === null && ! $this->isPlaceholderDate($data[$field])) {
                $warnings[] = __('students.import.warnings.date', ['field' => StudentTemplate::COLUMNS[$field][0], 'value' => $this->display($data[$field])]);
            }
        }

        $lookups = [
            'student_type' => fn ($value) => $this->lookup('types', $value),
            'information_source' => fn ($value) => $this->lookup('sources', $value),
            'plan' => fn ($value) => $this->lookup('plans', $value),
            'start_book' => fn ($value) => $this->bookId($value),
            'start_activity' => fn ($value) => $this->activityId($value),
        ];

        foreach ($lookups as $field => $resolve) {
            if ($this->filled($data, $field) && $resolve($data[$field]) === null) {
                $warnings[] = __('students.import.warnings.not_found', ['field' => StudentTemplate::COLUMNS[$field][0], 'value' => $this->display($data[$field])]);
            }
        }

        if ($this->filled($data, 'status') && $this->status($data['status']) === null) {
            $warnings[] = __('students.import.warnings.status', ['value' => $this->display($data['status'])]);
        }

        if ($this->filled($data, 'sales_advisor') && $this->lookup('advisors', $data['sales_advisor']) === null) {
            $warnings[] = __('students.import.warnings.new_advisor', ['value' => $this->display($data['sales_advisor'])]);
        }

        return [
            'line' => $line,
            'name' => trim(implode(' ', array_filter([$this->text($data, 'first_name'), $this->text($data, 'last_name'), $this->text($data, 'second_last_name')]))),
            'number' => $number,
            'action' => $existing !== null && $existing->school_id === $this->school->id ? 'update' : 'create',
            'email' => $email,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    // ── Escritura ──────────────────────────────────────────────────

    /** @param list<array<string, mixed>> $rows */
    private function importAdvisors(array $rows): int
    {
        $count = 0;

        foreach ($rows as $data) {
            $name = $this->text($data, 'name');

            if ($name === null) {
                continue;
            }

            $advisor = SalesAdvisor::withoutGlobalScope('school')->firstOrNew(['school_id' => $this->school->id, 'name' => Str::upper($name)]);
            $leftOn = $this->date($data, 'left_on');
            $advisor->fill([
                'advisor_position_id' => $this->filled($data, 'position') ? $this->lookup('positions', $data['position']) : $advisor->advisor_position_id,
                'hired_on' => $this->date($data, 'hired_on') ?? $advisor->hired_on,
                'left_on' => $leftOn,
                'is_active' => $leftOn === null,
            ])->save();

            $this->lookups['advisors'][StudentTemplate::normalize($advisor->name)] = $advisor->id;
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  string|null  $email  correo ya revisado (null si falta o tiene problema)
     */
    private function importRow(array $data, ?string $email): Student
    {
        $number = $this->text($data, 'enrollment_number');
        $number = $number === null ? null : Str::upper(preg_replace('/\s+/', '', $number));
        $student = $number === null ? null : Student::withoutGlobalScope('school')->where('school_id', $this->school->id)->where('enrollment_number', $number)->first();
        $creating = $student === null;

        if ($creating) {
            $number === null ? $number = $this->numbers->next($this->school, $this->date($data, 'registered_on')) : $this->numbers->register($this->school, $number);
            $student = new Student(['school_id' => $this->school->id, 'enrollment_number' => $number, 'status' => StudentStatus::Active]);
        }

        $status = $this->filled($data, 'status') ? ($this->status($data['status']) ?? StudentStatus::Active) : ($creating ? StudentStatus::Active : $student->status);
        $student->fill([
            'first_name' => Str::upper((string) $this->text($data, 'first_name')),
            'last_name' => Str::upper((string) $this->text($data, 'last_name')),
            'second_last_name' => ($value = $this->text($data, 'second_last_name')) === null ? null : Str::upper($value),
            'mobile_phone' => $this->phone($data, 'mobile_phone'),
            'landline_phone' => $this->phone($data, 'landline_phone') ?? $student->landline_phone,
            'email' => $email ?? $student->email,
            'birth_date' => $this->date($data, 'birth_date'),
            'student_type_id' => $this->filled($data, 'student_type') ? $this->lookup('types', $data['student_type']) : $student->student_type_id,
            'information_source_id' => $this->filled($data, 'information_source') ? $this->lookup('sources', $data['information_source']) : $student->information_source_id,
            'advertising_consent' => $this->bool($data, 'advertising_consent'),
            'learns_visual' => $this->bool($data, 'learns_visual'),
            'learns_auditory' => $this->bool($data, 'learns_auditory'),
            'learns_kinesthetic' => $this->bool($data, 'learns_kinesthetic'),
            'photo_source_url' => $this->photoUrl($data) ?? $student->photo_source_url,
            'notes' => $this->text($data, 'notes') ?? $student->notes,
            'course_starts_on' => $this->date($data, 'course_starts_on') ?? $student->course_starts_on,
            'withdrawn_on' => $this->date($data, 'withdrawn_on') ?? $student->withdrawn_on,
            'graduated_on' => $this->date($data, 'graduated_on') ?? $student->graduated_on,
        ])->fill($this->address($data))->save();

        $this->importEnrollment($student, $data);
        $this->importExtension($student, $data);

        $statusDate = $this->date($data, 'status_date') ?? ($status === StudentStatus::Withdrawn ? $this->date($data, 'withdrawn_on') : null);

        if ($creating || $student->status !== $status) {
            $this->changeStatus->handle($student, $status, $this->text($data, 'status_reason'), $statusDate);
        } else {
            $this->syncUser->handle($student);
        }

        $this->importAbsence($student, $data);
        $this->importGuardian($student, $data);
        $this->importAnswers($student, $data);

        return $student->refresh();
    }

    /** @param array<string, mixed> $data */
    private function importEnrollment(Student $student, array $data): void
    {
        $fields = ['contract_number', 'sales_advisor', 'plan', 'installments', 'registered_on', 'material_delivered_on',
            'material_serial', 'material_type', 'start_book', 'start_lesson', 'start_activity', 'reenrolled_on'];

        if (collect($fields)->every(fn (string $field) => ! $this->filled($data, $field))) {
            return;
        }

        $contract = $this->text($data, 'contract_number');
        $contract = $contract === null ? null : Str::upper($contract);
        $enrollment = $student->enrollments()
            ->when($contract !== null, fn ($query) => $query->where('contract_number', $contract))
            ->latest('id')->first()
            ?? new StudentEnrollment(['school_id' => $student->school_id, 'student_id' => $student->id]);

        $planId = $this->filled($data, 'plan') ? $this->lookup('plans', $data['plan']) : $enrollment->student_plan_id;
        $bookId = $this->filled($data, 'start_book') ? $this->bookId($data['start_book']) : $enrollment->start_book_id;
        $installments = $this->filled($data, 'installments') && is_numeric($data['installments']) ? (int) $data['installments'] : null;
        $material = $this->bool($data, 'material_assigned');

        $enrollment->fill([
            'contract_number' => $contract ?? $enrollment->contract_number,
            'sales_advisor_id' => $this->filled($data, 'sales_advisor') ? $this->advisorId($data['sales_advisor']) : $enrollment->sales_advisor_id,
            'student_plan_id' => $planId,
            'installments' => $installments ?? ($planId ? StudentPlan::find($planId)?->installments : null),
            'registered_on' => $this->date($data, 'registered_on'),
            'material_delivered_on' => $this->date($data, 'material_delivered_on') ?? $enrollment->material_delivered_on,
            'includes_toefl' => $this->bool($data, 'includes_toefl'),
            'includes_gold_card' => $this->bool($data, 'includes_gold_card'),
            'material_serial' => $material && ($serial = $this->text($data, 'material_serial')) !== null ? Str::upper(Str::limit($serial, 40, '')) : null,
            'material_type' => $material && in_array($type = Str::upper(Str::substr((string) $this->text($data, 'material_type'), 0, 1)), ['F', 'C'], true) ? $type : null,
            'material_assigned' => $material,
            'commission_applies' => $this->bool($data, 'commission_applies'),
            'start_book_id' => $bookId,
            'start_lesson_id' => $this->filled($data, 'start_lesson') && $bookId ? $this->lessonId($bookId, $data['start_lesson']) : $enrollment->start_lesson_id,
            'start_activity_id' => $this->filled($data, 'start_activity') ? $this->activityId($data['start_activity']) : $enrollment->start_activity_id,
            'reenrolled_on' => $this->date($data, 'reenrolled_on'),
        ])->save();
    }

    /**
     * Días de ampliación de plazo → movimiento "Extensión de tiempo" (hasta 120 días). Se reconoce por su
     * fecha para que volver a importar no lo duplique.
     *
     * @param  array<string, mixed>  $data
     */
    private function importExtension(Student $student, array $data): void
    {
        $days = $this->filled($data, 'extension_days') && is_numeric($data['extension_days']) ? (int) $data['extension_days'] : 0;

        if ($days < 1) {
            return;
        }

        $occurredOn = $this->date($data, 'reenrolled_on') ?? $this->date($data, 'registered_on') ?? $student->course_starts_on ?? now(config('appingles.timezone'));

        StudentTermMovement::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $student->school_id, 'student_id' => $student->id, 'type' => StudentTermMovementType::Extension, 'occurred_on' => $occurredOn->toDateString()],
            ['days' => min(StudentTermMovementType::Extension->maxDays(), $days), 'user_id' => auth()->id()],
        );
    }

    /**
     * Domicilio por partes (columnas separadas) o, si solo viene en una línea, separado con AddressParser.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function address(array $data): array
    {
        $this->addressParser ??= new AddressParser($this->school->state_id);
        $parts = ['street', 'exterior_number', 'interior_number', 'neighborhood', 'postal_code'];
        $line = Str::limit((string) $this->text($data, 'address'), 250, '') ?: null;

        if (collect([...$parts, 'municipality', 'state'])->contains(fn (string $field) => $this->filled($data, $field))) {
            return [
                ...collect($parts)->mapWithKeys(fn (string $field) => [$field => ($value = $this->text($data, $field)) === null ? null : Str::upper(Str::limit($value, $field === 'postal_code' ? 5 : 150, ''))])->all(),
                ...$this->addressParser->locate($this->text($data, 'municipality'), $this->text($data, 'state')),
                'address' => $line,
                'address_needs_review' => false,
            ];
        }

        return $line === null ? [] : ['address' => $line, ...$this->addressParser->parse($line)];
    }

    /** @param array<string, mixed> $data */
    private function importAbsence(Student $student, array $data): void
    {
        $from = $this->date($data, 'absence_from');

        if ($from === null) {
            return;
        }

        StudentAbsence::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $student->school_id, 'student_id' => $student->id, 'starts_on' => $from->toDateString()],
            ['ends_on' => $this->date($data, 'absence_to')?->toDateString(), 'user_id' => auth()->id()],
        );
    }

    /** @param array<string, mixed> $data */
    private function importGuardian(Student $student, array $data): void
    {
        $name = $this->text($data, 'guardian_name');

        if ($name === null) {
            return;
        }

        $phone = $this->phone($data, 'guardian_phone');
        $guardian = Guardian::withoutGlobalScope('school')->where('school_id', $student->school_id)->where('name', Str::upper($name))
            ->when($phone !== null, fn ($query) => $query->where('mobile_phone', $phone))
            ->first()
            ?? new Guardian(['school_id' => $student->school_id, 'name' => Str::upper($name)]);

        $guardianEmail = $this->text($data, 'guardian_email');
        $guardian->fill([
            'mobile_phone' => $phone ?? $guardian->mobile_phone,
            'email' => $guardianEmail !== null && filter_var($guardianEmail, FILTER_VALIDATE_EMAIL) ? $guardianEmail : $guardian->email,
            'receives' => $this->guardianReports($this->text($data, 'guardian_receives')) ?: $guardian->receives,
        ])->save();

        $student->guardians()->syncWithoutDetaching([$guardian->id]);
    }

    /** @param array<string, mixed> $data */
    private function importAnswers(Student $student, array $data): void
    {
        foreach ($data as $key => $value) {
            if (! str_starts_with($key, 'q:') || ($answer = $this->display($value)) === '') {
                continue;
            }

            $question = $this->questions->get((int) substr($key, 2));

            if ($question === null) {
                continue;
            }

            StudentSurveyAnswer::withoutGlobalScope('school')->updateOrCreate(
                ['student_id' => $student->id, 'survey_question_id' => $question->id],
                ['school_id' => $student->school_id, 'answer' => Str::limit($this->matchOption($question, $answer), 500, '')],
            );
        }
    }

    // ── Valores ────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    private function filled(array $data, string $key): bool
    {
        return isset($data[$key]) && $this->display($data[$key]) !== '';
    }

    /** @param array<string, mixed> $data */
    private function text(array $data, string $key): ?string
    {
        $value = $this->display($data[$key] ?? null);

        return $value === '' || in_array(Str::upper($value), ['#N/A', '#REF!', '#NAME?', '#VALUE!', 'NA', 'N/A'], true) ? null : $value;
    }

    private function display(mixed $value): string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_float($value) && floor($value) === $value => (string) (int) $value,
            is_bool($value) => $value ? '1' : '0',
            default => trim((string) $value),
        };
    }

    /** @param array<string, mixed> $data */
    private function phone(array $data, string $key): ?string
    {
        $phone = $this->text($data, $key);

        if ($phone === null) {
            return null;
        }

        return Str::limit((string) preg_replace('/[^0-9 +()\-]/', '', $phone), 20, '') ?: null;
    }

    /** @param array<string, mixed> $data */
    private function bool(array $data, string $key): bool
    {
        $value = StudentTemplate::normalize($this->display($data[$key] ?? ''));

        return in_array($value, ['si', 's', 'yes', 'y', '1', 'x', 'verdadero', 'true', 'ok'], true);
    }

    /** Fechas de Excel (celda de fecha o número de serie) o texto dd/mm/aaaa, aaaa-mm-dd. 31/12/2100 o posterior = sin fecha. */
    private function date(array $data, string $key): ?CarbonImmutable
    {
        $value = $data[$key] ?? null;

        try {
            $date = match (true) {
                $value instanceof DateTimeInterface => CarbonImmutable::instance($value),
                is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d{4,5}(\.\d+)?$/', $value)) => CarbonImmutable::create(1899, 12, 30)->addDays((int) $value),
                is_string($value) && preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $value, $m) => CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1]),
                is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) => CarbonImmutable::parse(substr($value, 0, 10)),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }

        return $date === null || $date->year >= 2100 || $date->year < 1900 ? null : $date->startOfDay();
    }

    private function isPlaceholderDate(mixed $value): bool
    {
        return in_array(Str::upper($this->display($value)), ['PEND', 'PENDIENTE', '#REF!', '#N/A', '#NAME?'], true)
            || ($value instanceof DateTimeInterface && (int) $value->format('Y') >= 2100)
            || (is_numeric($value) && (int) $value >= 73050);
    }

    private function status(mixed $value): ?StudentStatus
    {
        $normalized = StudentTemplate::normalize($this->display($value));

        foreach (StudentStatus::cases() as $status) {
            if (in_array($normalized, [$status->value, StudentTemplate::normalize($status->label())], true)) {
                return $status;
            }
        }

        return null;
    }

    private function photoUrl(array $data): ?string
    {
        $url = $this->text($data, 'photo_url');

        return $url !== null && preg_match('#^https://#i', $url) && strlen($url) <= 500 ? $url : null;
    }

    /** Una respuesta de opción se guarda con el texto exacto de la opción si coincide (sin acentos ni mayúsculas). */
    private function matchOption(SurveyQuestion $question, string $answer): string
    {
        if ($question->type !== SurveyQuestionType::Choice) {
            return $answer;
        }

        $normalized = StudentTemplate::normalize($answer);

        foreach ($question->options ?? [] as $option) {
            if (StudentTemplate::normalize($option) === $normalized) {
                return $option;
            }
        }

        return $answer;
    }

    /** @return list<string> */
    private function guardianReports(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $normalized = StudentTemplate::normalize($value);

        return array_values(array_filter([
            str_contains($normalized, 'fa') || str_contains($normalized, 'ficha') || str_contains($normalized, 'academ') ? 'academic_record' : null,
            str_contains($normalized, 'pago') || str_contains($normalized, 'cobranza') ? 'payments' : null,
            str_contains($normalized, 'asistencia') ? 'attendance' : null,
        ]));
    }

    private function lookup(string $catalog, mixed $value): ?int
    {
        $this->lookups[$catalog] ??= match ($catalog) {
            'types' => StudentType::query()->ofCatalog($this->school->id)->get()
                ->flatMap(fn (StudentType $type) => [StudentTemplate::normalize($type->code) => $type->id, StudentTemplate::normalize($type->name) => $type->id])->all(),
            'sources' => InformationSource::query()->ofCatalog($this->school->id)->get()
                ->mapWithKeys(fn (InformationSource $source) => [StudentTemplate::normalize($source->name) => $source->id])->all(),
            'plans' => StudentPlan::query()->ofCatalog($this->school->id)->get()
                ->flatMap(fn (StudentPlan $plan) => [StudentTemplate::normalize($plan->code) => $plan->id, StudentTemplate::normalize($plan->name) => $plan->id])->all(),
            'positions' => AdvisorPosition::query()->ofCatalog($this->school->id)->get()
                ->mapWithKeys(fn (AdvisorPosition $position) => [StudentTemplate::normalize($position->name) => $position->id])->all(),
            'advisors' => SalesAdvisor::withoutGlobalScope('school')->where('school_id', $this->school->id)->get()
                ->mapWithKeys(fn (SalesAdvisor $advisor) => [StudentTemplate::normalize($advisor->name) => $advisor->id])->all(),
        };

        return $this->lookups[$catalog][StudentTemplate::normalize($this->display($value))] ?? null;
    }

    /** El asesor que no existe se da de alta en la escuela. */
    private function advisorId(mixed $value): ?int
    {
        $name = Str::upper($this->display($value));

        if ($name === '') {
            return null;
        }

        if (($id = $this->lookup('advisors', $name)) !== null) {
            return $id;
        }

        $advisor = SalesAdvisor::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'name' => Str::limit($name, 120, ''), 'is_active' => true]);

        return $this->lookups['advisors'][StudentTemplate::normalize($advisor->name)] = $advisor->id;
    }

    private function bookId(mixed $level): ?int
    {
        return is_numeric($level) ? Book::query()->ofCatalog($this->school->id)->where('level', (int) $level)->value('id') : null;
    }

    private function lessonId(int $bookId, mixed $number): ?int
    {
        return is_numeric($number) ? Lesson::query()->ofCatalog($this->school->id)->where('book_id', $bookId)->where('number', (int) $number)->value('id') : null;
    }

    private function activityId(mixed $number): ?int
    {
        return is_numeric($number) ? Activity::query()->ofCatalog($this->school->id)->where('number', (int) $number)->value('id') : null;
    }
}
