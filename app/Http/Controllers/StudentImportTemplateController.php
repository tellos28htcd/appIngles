<?php

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Enums\SurveyQuestionType;
use App\Models\Activity;
use App\Models\AdvisorPosition;
use App\Models\Book;
use App\Models\InformationSource;
use App\Models\Lesson;
use App\Models\SalesAdvisor;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentType;
use App\Models\SurveyQuestion;
use App\Support\StudentTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Validation\CellReference;
use OpenSpout\Writer\XLSX\Validation\Rules\ListValidationRule;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Plantilla .xlsx para importar alumnos: hoja Alumnos (con listas desplegables
 * de los catálogos de la escuela y las preguntas de su encuesta), hoja
 * Asesores, hoja Valores (opciones válidas) e Instrucciones.
 */
class StudentImportTemplateController extends Controller
{
    private const DATA_ROWS = 3000;

    public function __invoke(): BinaryFileResponse
    {
        Gate::authorize('create', Student::class);

        $schoolId = auth()->user()->school_id;
        $questions = SurveyQuestion::query()->ofCatalog($schoolId)->where('is_active', true)->orderBy('sort_order')->get();

        $values = [
            'types' => StudentType::query()->ofCatalog($schoolId)->where('is_active', true)->orderBy('code')->pluck('code')->all(),
            'sources' => InformationSource::query()->ofCatalog($schoolId)->where('is_active', true)->orderBy('name')->pluck('name')->all(),
            'statuses' => collect(StudentStatus::cases())->map(fn (StudentStatus $status) => $status->label())->all(),
            'advisors' => SalesAdvisor::query()->where('school_id', $schoolId)->orderBy('name')->pluck('name')->all(),
            'plans' => StudentPlan::query()->ofCatalog($schoolId)->where('is_active', true)->orderBy('code')->pluck('code')->all(),
            'positions' => AdvisorPosition::query()->ofCatalog($schoolId)->where('is_active', true)->orderBy('rank')->pluck('name')->all(),
            'material_types' => ['F', 'C'],
            'bool' => [__('audit.yes'), __('audit.no')],
        ];

        foreach ($questions->where('type', SurveyQuestionType::Choice) as $question) {
            $values['q:'.$question->id] = $question->options ?? [];
        }

        $valueColumns = array_keys($values);
        $listFor = function (string $type) use ($valueColumns, $values): ?CellReference {
            $key = match (true) {
                $type === 'bool' => 'bool',
                str_starts_with($type, 'list:') => substr($type, 5),
                default => $type,
            };
            $index = array_search($key, $valueColumns, true);

            return $index === false || $values[$key] === []
                ? null
                : new CellReference($index, 2, $index, count($values[$key]) + 1, true, StudentTemplate::VALUES_SHEET);
        };

        $columns = [
            ...collect(StudentTemplate::COLUMNS)->map(fn (array $column) => $column)->all(),
            ...$questions->mapWithKeys(fn (SurveyQuestion $question) => ['q:'.$question->id => [
                $question->question, $question->type === SurveyQuestionType::Choice ? 'q:'.$question->id : 'text',
            ]])->all(),
        ];

        $options = new Options;
        $index = 0;
        foreach ($columns as $key => [, $type]) {
            $options->setColumnWidth($type === 'date' ? 16 : (str_starts_with((string) $key, 'q:') ? 28 : 20), $index + 1);

            if (($rule = $listFor($type)) !== null) {
                $options->addValidation($index, 2, $index, self::DATA_ROWS, new ListValidationRule($rule));
            }
            $index++;
        }

        $advisorIndex = 0;
        foreach (StudentTemplate::ADVISOR_COLUMNS as [, $type]) {
            if (($rule = $listFor($type)) !== null) {
                $options->addValidation($advisorIndex, 2, $advisorIndex, 500, new ListValidationRule($rule), sheetIndex: 1);
            }
            $advisorIndex++;
        }

        $path = tempnam(sys_get_temp_dir(), 'plantilla-alumnos');
        $header = new Style(fontBold: true, backgroundColor: 'E8EEFF');
        $writer = new Writer($options);
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName(StudentTemplate::STUDENTS_SHEET)->setSheetView(new SheetView(freezeRow: 2, freezeColumn: 'D'));
        $writer->addRow(Row::fromValuesWithStyle(array_column($columns, 0), $header));

        $writer->addNewSheetAndMakeItCurrent()->setName(StudentTemplate::ADVISORS_SHEET)->setSheetView(new SheetView(freezeRow: 2));
        $writer->addRow(Row::fromValuesWithStyle(array_column(StudentTemplate::ADVISOR_COLUMNS, 0), $header));

        $writer->addNewSheetAndMakeItCurrent()->setName(StudentTemplate::VALUES_SHEET);
        $writer->addRow(Row::fromValuesWithStyle(array_map(fn (string $key) => $this->valuesHeader($key, $questions), $valueColumns), $header));
        for ($row = 0, $max = max(array_map('count', $values)); $row < $max; $row++) {
            $writer->addRow(Row::fromValues(array_map(fn (string $key) => $values[$key][$row] ?? '', $valueColumns)));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName(StudentTemplate::INSTRUCTIONS_SHEET);
        foreach ($this->instructions($schoolId) as $line) {
            $writer->addRow(Row::fromValues([$line]));
        }

        $writer->close();

        return response()->download($path, 'plantilla-alumnos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** @param Collection<int, SurveyQuestion> $questions */
    private function valuesHeader(string $key, $questions): string
    {
        if (str_starts_with($key, 'q:')) {
            return (string) $questions->firstWhere('id', (int) substr($key, 2))?->question;
        }

        return __("students.import.values.$key");
    }

    /** @return list<string> */
    private function instructions(int $schoolId): array
    {
        $books = Book::query()->ofCatalog($schoolId)->orderBy('level')->get();

        return [
            ...__('students.import.instructions'),
            '',
            __('students.import.instructions_books'),
            ...$books->map(fn (Book $book) => "  {$book->level} = {$book->name}")->all(),
            '',
            __('students.import.instructions_lessons'),
            ...Lesson::query()->ofCatalog($schoolId)->whereIn('book_id', $books->modelKeys())->orderBy('sort_order')->get()
                ->unique('number')->map(fn (Lesson $lesson) => "  {$lesson->number} = {$lesson->name}")->values()->all(),
            '',
            __('students.import.instructions_activities'),
            ...Activity::query()->ofCatalog($schoolId)->orderBy('number')->get()
                ->map(fn (Activity $activity) => "  {$activity->number} = {$activity->code} · {$activity->description}")->all(),
        ];
    }
}
