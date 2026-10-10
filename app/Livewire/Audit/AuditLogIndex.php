<?php

namespace App\Livewire\Audit;

use App\Enums\LoginEvent;
use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bitácora (solo consulta): cambios (audit_logs) y accesos (login_logs).
 * - Plataforma → Bitácora: el Super Admin ve todas las escuelas y la plataforma.
 * - Configuración → Bitácora: el administrador ve solo su escuela.
 */
#[Layout('layouts.app')]
class AuditLogIndex extends Component
{
    use WithPagination;

    public const PER_PAGE_OPTIONS = [25, 50, 100];

    public const DEFAULT_DAYS = 30;

    public const TABS = ['cambios', 'accesos'];

    /** true = Plataforma (todas las escuelas); false = solo la escuela del usuario. */
    #[Locked]
    public bool $platform = false;

    #[Url(as: 'vista', except: 'cambios')]
    public string $tab = 'cambios';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'escuela', except: '')]
    public string $school = '';

    #[Url(as: 'modulo', except: '')]
    public string $module = '';

    #[Url(as: 'accion', except: '')]
    public string $event = '';

    #[Url(as: 'desde')]
    public string $from = '';

    #[Url(as: 'hasta', except: '')]
    public string $to = '';

    #[Url(as: 'por_pagina', except: 25)]
    public int $perPage = 25;

    public ?int $viewingId = null;

    public function mount(): void
    {
        $this->platform = request()->routeIs('audit-logs.index');
        $actor = auth()->user();

        if (! $this->platform && $actor->isPlatformAdmin()) {
            session()->flash('toast', ['type' => 'warning', 'message' => __('audit.platform_redirect')]);
            $this->redirectRoute('audit-logs.index', navigate: true);

            return;
        }

        $this->authorizeView();
        $this->sanitizeFilters();

        if ($this->from === '') {
            $this->from = now(config('appingles.timezone'))->subDays(self::DEFAULT_DAYS)->toDateString();
        }
    }

    public function updated(string $property): void
    {
        $this->sanitizeFilters();

        if ($property === 'tab') {
            $this->reset('event', 'module');
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'school', 'module', 'event', 'to');
        $this->from = now(config('appingles.timezone'))->subDays(self::DEFAULT_DAYS)->toDateString();
        $this->resetPage();
    }

    public function showDetail(int $logId): void
    {
        $this->authorizeView();
        $this->viewingId = $this->auditQuery()->whereKey($logId)->firstOrFail()->id;
    }

    #[Computed]
    public function viewingLog(): ?AuditLog
    {
        return $this->viewingId
            ? $this->auditQuery()->with(['user:id,name,email', 'school:id,code,name'])->find($this->viewingId)
            : null;
    }

    /** @return LengthAwarePaginator<int, AuditLog> */
    #[Computed]
    public function auditLogs(): LengthAwarePaginator
    {
        return $this->filteredAuditQuery()
            ->with(['user:id,name,email', 'school:id,code,name'])
            ->paginate($this->perPage);
    }

    /** @return LengthAwarePaginator<int, LoginLog> */
    #[Computed]
    public function loginLogs(): LengthAwarePaginator
    {
        return $this->filteredLoginQuery()
            ->with(['user:id,name', 'school:id,code,name'])
            ->paginate($this->perPage);
    }

    /** Descarga en CSV (abre en Excel) lo que muestran los filtros actuales. */
    public function export(): StreamedResponse
    {
        $this->authorizeView();
        $this->sanitizeFilters();

        $timezone = config('appingles.timezone');
        $isChanges = $this->tab === 'cambios';
        $filename = 'bitacora-'.($isChanges ? 'cambios' : 'accesos').'-'.now($timezone)->format('Y-m-d-His').'.csv';

        $query = $isChanges
            ? $this->filteredAuditQuery()->with(['user:id,name,email', 'school:id,code,name'])
            : $this->filteredLoginQuery()->with(['user:id,name', 'school:id,code,name']);

        return response()->streamDownload(function () use ($query, $isChanges, $timezone): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel respeta acentos.
            fputcsv($out, __($isChanges ? 'audit.export.changes_header' : 'audit.export.logins_header'));

            foreach ($query->reorder()->lazyByIdDesc(500) as $log) {
                fputcsv($out, $isChanges ? $this->auditRow($log, $timezone) : $this->loginRow($log, $timezone));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<string> */
    private function auditRow(AuditLog $log, string $timezone): array
    {
        return [
            $log->created_at->timezone($timezone)->format('Y-m-d H:i:s'),
            $this->schoolName($log->school),
            $log->user?->name ?? __('audit.system_actor'),
            $log->user?->email ?? '',
            $log->moduleLabel(),
            $log->eventLabel(),
            $log->subject(),
            collect($log->changes())->map(fn (array $change) => "{$change['field']}: {$change['old']} → {$change['new']}")->implode(' | '),
            (string) $log->ip_address,
        ];
    }

    /** @return list<string> */
    private function loginRow(LoginLog $log, string $timezone): array
    {
        return [
            $log->created_at->timezone($timezone)->format('Y-m-d H:i:s'),
            $this->schoolName($log->school),
            $log->email,
            $log->user?->name ?? '',
            $log->event->label(),
            (string) $log->ip_address,
            (string) $log->user_agent,
        ];
    }

    private function schoolName(?School $school): string
    {
        return $school ? "{$school->code} · {$school->name}" : __('audit.platform');
    }

    private function authorizeView(): void
    {
        $this->authorize($this->platform ? 'viewPlatform' : 'viewSchool', AuditLog::class);
    }

    /** Fuera de Plataforma, siempre y solo la escuela del usuario (además del global scope). */
    private function auditQuery(): Builder
    {
        return $this->scoped(AuditLog::query());
    }

    private function scoped(Builder $query): Builder
    {
        if (! $this->platform) {
            return $query->where($query->qualifyColumn('school_id'), auth()->user()->school_id);
        }

        return $query->when($this->school !== '', fn (Builder $q) => $this->school === 'plataforma'
            ? $q->whereNull('school_id')
            : $q->where('school_id', (int) $this->school));
    }

    private function filteredAuditQuery(): Builder
    {
        $term = trim($this->search);
        $modelClass = $this->module !== '' ? AuditLog::modelClassFor($this->module) : null;

        return $this->withinDates($this->auditQuery())
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('auditable_label', 'like', "%{$term}%")
                ->orWhereHas('user', fn (Builder $user) => $user
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"))))
            ->when($modelClass !== null, fn (Builder $query) => $query->where('auditable_type', $modelClass))
            ->when($this->event !== '', fn (Builder $query) => $query->where('event', $this->event))
            ->latest('id');
    }

    private function filteredLoginQuery(): Builder
    {
        $term = mb_strtolower(trim($this->search));

        return $this->withinDates($this->scoped(LoginLog::query()))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('email', 'like', "%{$term}%")
                ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$term}%"))))
            ->when($this->event !== '', fn (Builder $query) => $query->where('event', $this->event))
            ->latest('id');
    }

    /** Las fechas se capturan en hora de la escuela (CDMX) y se guardan en UTC. */
    private function withinDates(Builder $query): Builder
    {
        $timezone = config('appingles.timezone');

        return $query
            ->when($this->from !== '', fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($this->from, $timezone)->startOfDay()->utc()))
            ->when($this->to !== '', fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($this->to, $timezone)->endOfDay()->utc()));
    }

    /** Las propiedades públicas llegan del navegador: solo se aceptan valores conocidos. */
    private function sanitizeFilters(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'cambios';
        }

        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 25;
        }

        if (! $this->platform || ($this->school !== 'plataforma' && ! ctype_digit($this->school))) {
            $this->school = '';
        }

        if ($this->module !== '' && AuditLog::modelClassFor($this->module) === null) {
            $this->module = '';
        }

        if ($this->tab === 'accesos' && $this->event !== '' && LoginEvent::tryFrom($this->event) === null) {
            $this->event = '';
        }

        if ($this->tab === 'cambios' && $this->event !== '' && ! array_key_exists($this->event, __('audit.events'))) {
            $this->event = '';
        }

        foreach (['from', 'to'] as $date) {
            if ($this->{$date} !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->{$date})) {
                $this->{$date} = '';
            }
        }
    }

    /** @return array<string, string> */
    #[Computed]
    public function schoolOptions(): array
    {
        return ['plataforma' => __('audit.platform')] + School::query()->orderBy('name')->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (School $school) => [(string) $school->id => "{$school->code} · {$school->name}"])
            ->all();
    }

    public function render(): View
    {
        $isChanges = $this->tab === 'cambios';

        return view('livewire.audit.audit-log-index', [
            'isChanges' => $isChanges,
            'tabs' => ['cambios' => __('audit.tabs.changes'), 'accesos' => __('audit.tabs.logins')],
            'moduleOptions' => AuditLog::moduleOptions(),
            'eventOptions' => $isChanges
                ? __('audit.events')
                : collect(LoginEvent::cases())->mapWithKeys(fn (LoginEvent $event) => [$event->value => $event->label()])->all(),
            'logs' => $isChanges ? $this->auditLogs : $this->loginLogs,
            'hasFilters' => $this->search !== '' || $this->school !== '' || $this->module !== '' || $this->event !== '' || $this->to !== '',
            'timezone' => config('appingles.timezone'),
        ])->title(__($this->platform ? 'audit.title_platform' : 'audit.title'));
    }
}
