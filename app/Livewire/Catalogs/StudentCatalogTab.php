<?php

namespace App\Livewire\Catalogs;

use App\Enums\AttendanceEffect;
use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\AdvisorPosition;
use App\Models\CancellationReason;
use App\Models\InformationSource;
use App\Models\StudentPlan;
use App\Models\StudentType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pestañas sencillas del catálogo de alumnos (base o copia de una escuela):
 * planes, origen de la información, tipos de alumno, puestos de asesor y motivos de
 * cancelación de la agenda. Cada una se describe en CATALOGS: modelo, tabla, orden y
 * campos; un campo "select" lleva en la 2.ª posición el enum de sus opciones.
 */
class StudentCatalogTab extends Component
{
    use ManagesCatalog;

    /**
     * pestaña => [modelo, tabla, orden, campos (campo => [tipo, máximo, ¿único?])]
     *
     * @var array<string, array{model: class-string<Model>, table: string, order: string, fields: array<string, array{0: string, 1: int|class-string, 2?: bool}>}>
     */
    public const CATALOGS = [
        'planes' => ['model' => StudentPlan::class, 'table' => 'student_plans', 'order' => 'code', 'fields' => [
            'code' => ['code', 10, true], 'name' => ['text', 80], 'installments' => ['number', 60],
        ]],
        'origenes' => ['model' => InformationSource::class, 'table' => 'information_sources', 'order' => 'name', 'fields' => [
            'name' => ['text', 80, true], 'channel' => ['text', 80], 'short_name' => ['text', 20],
        ]],
        'tipos' => ['model' => StudentType::class, 'table' => 'student_types', 'order' => 'code', 'fields' => [
            'code' => ['code', 10, true], 'name' => ['text', 80],
        ]],
        'puestos' => ['model' => AdvisorPosition::class, 'table' => 'advisor_positions', 'order' => 'rank', 'fields' => [
            'name' => ['text', 80, true], 'rank' => ['number', 99],
        ]],
        'cancelaciones' => ['model' => CancellationReason::class, 'table' => 'cancellation_reasons', 'order' => 'sort_order', 'fields' => [
            'name' => ['text', 80, true], 'attendance' => ['select', AttendanceEffect::class], 'sort_order' => ['number', 255],
        ]],
    ];

    #[Locked]
    public string $catalog = 'planes';

    public ?bool $editing = null;

    public ?int $recordId = null;

    /** @var array<string, string|int|null> */
    public array $values = [];

    public function mount(?int $schoolId, string $catalog): void
    {
        abort_unless(isset(self::CATALOGS[$catalog]), 404);

        $this->schoolId = $schoolId;
        $this->catalog = $catalog;
        $this->authorizeCatalog();
    }

    /** @return array{model: class-string<Model>, table: string, order: string, fields: array<string, array{0: string, 1: int|class-string, 2?: bool}>} */
    private function config(): array
    {
        return self::CATALOGS[$this->catalog];
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $record = $id ? $this->findInCatalog($this->config()['model'], $id) : null;
        $this->recordId = $record?->id;
        $this->values = collect($this->config()['fields'])
            ->mapWithKeys(fn (array $field, string $name) => [$name => match (true) {
                $field[0] === 'select' => $record?->getAttribute($name)?->value ?? $field[1]::cases()[0]->value,
                default => $record?->getAttribute($name) ?? ($field[0] === 'number' ? 0 : ''),
            }])
            ->all();
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();
        $config = $this->config();

        $rules = collect($config['fields'])->mapWithKeys(function (array $field, string $name) use ($config): array {
            [$type, $max] = $field;
            $rules = match ($type) {
                'number' => ['required', 'integer', 'min:0', "max:$max"],
                'select' => ['required', Rule::enum($max)],
                'code' => ['required', 'string', "max:$max", 'regex:/^[A-Z0-9_\-]+$/'],
                default => [$name === 'name' || ($field[2] ?? false) ? 'required' : 'nullable', 'string', "max:$max"],
            };

            if ($field[2] ?? false) {
                $rules[] = $this->uniqueInCatalog($config['table'], $name, $this->recordId);
            }

            return ["values.$name" => $rules];
        })->all();

        foreach ($config['fields'] as $name => [$type]) {
            $value = trim((string) ($this->values[$name] ?? ''));
            $this->values[$name] = $type === 'code' ? Str::upper($value) : $value;
        }

        $this->validate($rules, [], collect($config['fields'])
            ->mapWithKeys(fn (array $field, string $name) => ["values.$name" => Str::lower(__("catalogs.student.fields.$name"))])
            ->all());

        $data = collect($config['fields'])->mapWithKeys(fn (array $field, string $name) => [
            $name => $field[0] === 'number' ? (int) $this->values[$name] : ($this->values[$name] === '' ? null : $this->values[$name]),
        ])->all();

        $record = $this->recordId
            ? $this->findInCatalog($config['model'], $this->recordId)
            : new $config['model'](['school_id' => $this->schoolId, 'is_active' => true]);
        $record->fill($data)->save();

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('catalogs.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive($this->config()['model'], $id);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion($this->config()['model'], $id);
    }

    public function delete(): void
    {
        $model = $this->config()['model'];
        $this->deleteConfirmed([class_basename($model) => $model]);
    }

    /** @return Collection<int, Model> */
    #[Computed]
    public function records(): Collection
    {
        return $this->catalog($this->config()['model'])->orderBy($this->config()['order'])->orderBy('id')->get();
    }

    public function render(): View
    {
        return view('livewire.catalogs.student-catalog-tab', [
            'fields' => $this->config()['fields'],
            'selectOptions' => collect($this->config()['fields'])->filter(fn (array $field) => $field[0] === 'select')
                ->map(fn (array $field) => collect($field[1]::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all())->all(),
        ]);
    }
}
