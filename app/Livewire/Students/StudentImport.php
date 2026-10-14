<?php

namespace App\Livewire\Students;

use App\Actions\Students\ImportStudents;
use App\Models\School;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Importar alumnos: 1) descargar la plantilla, 2) subirla llena y revisar
 * errores por fila, 3) importar lo que no tiene errores. Nunca envía correos.
 */
#[Layout('layouts.app')]
class StudentImport extends Component
{
    use WithFileUploads;

    /** Filas con errores o avisos que se muestran en la revisión. */
    private const MAX_LISTED = 300;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /** @var array{total: int, create: int, update: int, with_errors: int, with_warnings: int, advisors: int, unknown_columns: list<string>, missing_columns: list<string>, rows: list<array<string, mixed>>}|null */
    public ?array $preview = null;

    /** @var array{created: int, updated: int, skipped: int, advisors: int, photos: int, failed: list<int>}|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->authorize('create', Student::class);
    }

    public function updatedFile(): void
    {
        $this->reset('preview', 'result');
        $this->validateFile();
    }

    public function review(ImportStudents $import): void
    {
        $this->authorize('create', Student::class);
        $this->validateFile();

        $preview = $import->preview($this->school(), $this->file->getRealPath());
        $rows = collect($preview['rows']);

        $this->preview = [
            'total' => $rows->count(),
            'create' => $rows->where('action', 'create')->filter(fn ($row) => $row['errors'] === [])->count(),
            'update' => $rows->where('action', 'update')->filter(fn ($row) => $row['errors'] === [])->count(),
            'with_errors' => $rows->filter(fn ($row) => $row['errors'] !== [])->count(),
            'with_warnings' => $rows->filter(fn ($row) => $row['errors'] === [] && $row['warnings'] !== [])->count(),
            'advisors' => $preview['advisors'],
            'unknown_columns' => $preview['unknown_columns'],
            'missing_columns' => $preview['missing_columns'],
            'rows' => $rows->filter(fn ($row) => $row['errors'] !== [] || $row['warnings'] !== [])
                ->sortByDesc(fn ($row) => $row['errors'] !== [])
                ->take(self::MAX_LISTED)->values()->all(),
        ];
    }

    public function import(ImportStudents $import): void
    {
        $this->authorize('create', Student::class);
        $this->validateFile();
        abort_if($this->preview === null || $this->preview['missing_columns'] !== [], 422);

        $this->result = $import->import($this->school(), $this->file->getRealPath());
        $this->preview = null;
        $this->file = null;

        $this->dispatch('toast', type: 'success', message: __('students.import.done', [
            'created' => $this->result['created'], 'updated' => $this->result['updated'],
        ]));
    }

    public function restart(): void
    {
        $this->reset('file', 'preview', 'result');
    }

    private function validateFile(): void
    {
        $this->validate(
            ['file' => ['required', 'file', 'extensions:xlsx', 'max:10240']],
            ['file.extensions' => __('students.import.errors.file_type')],
            ['file' => __('students.import.file')],
        );
    }

    private function school(): School
    {
        return School::findOrFail(auth()->user()->school_id);
    }

    public function render(): View
    {
        return view('livewire.students.student-import')->title(__('students.import.title'));
    }
}
