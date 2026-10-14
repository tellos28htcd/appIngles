<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('students.import.title')" :subtitle="__('students.import.subtitle')">
        <x-slot:actions>
            <a href="{{ route('students.index') }}" wire:navigate
               class="inline-flex h-11 items-center justify-center rounded-md px-4 text-[15px] font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                {{ __('students.actions.back') }}
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid max-w-5xl gap-4 md:gap-6 lg:grid-cols-2">
        {{-- Paso 1 --}}
        <x-ui.card :title="__('students.import.step1')" :description="__('students.import.step1_hint')">
            <a href="{{ route('students.template') }}"
               class="inline-flex h-11 w-fit items-center justify-center gap-2 rounded-md border-[1.5px] border-line-strong bg-white px-5 text-[15px] font-bold text-ink-900 transition hover:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                <x-ui.icon name="download" class="size-4" />{{ __('students.import.download_template') }}
            </a>
        </x-ui.card>

        {{-- Paso 2 --}}
        <x-ui.card :title="__('students.import.step2')" :description="__('students.import.step2_hint')">
            <label for="import-file" class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-line-strong bg-surface px-4 py-6 text-center hover:border-primary-400 focus-within:ring-4 focus-within:ring-primary-200">
                <x-ui.icon name="download" class="size-6 rotate-180 text-primary-600" />
                <span class="text-[15px] font-bold text-primary-700">{{ $file ? $file->getClientOriginalName() : __('students.import.choose_file') }}</span>
                <span class="text-xs text-ink-500">{{ __('students.import.file_hint') }}</span>
                <input id="import-file" type="file" wire:model="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="sr-only">
            </label>
            <span wire:loading wire:target="file" class="text-sm text-ink-500">{{ __('students.import.uploading') }}</span>
            @error('file')
                <p class="text-[13px] font-medium text-danger-700">{{ $message }}</p>
            @enderror
            @if ($file && ! $errors->has('file') && $preview === null)
                <x-ui.button wire:click="review" wire:loading.attr="disabled" wire:target="review" class="w-fit">{{ __('students.import.review') }}</x-ui.button>
            @endif
        </x-ui.card>
    </div>

    {{-- Revisión --}}
    @if ($preview !== null)
        <x-ui.card :title="__('students.import.step3')" class="max-w-5xl">
            @if ($preview['missing_columns'] !== [])
                <div role="alert" class="rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">
                    {{ __('students.import.missing_columns', ['columns' => collect($preview['missing_columns'])->map(fn ($key) => \App\Support\StudentTemplate::COLUMNS[$key][0])->implode(', ')]) }}
                </div>
            @endif

            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach (['total', 'create', 'update', 'with_warnings', 'with_errors'] as $key)
                    <div class="flex flex-col rounded-lg border border-line p-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-ink-500">{{ __("students.import.summary.$key") }}</dt>
                        <dd @class(['text-2xl font-extrabold tabular-nums', 'text-danger-700' => $key === 'with_errors' && $preview[$key] > 0, 'text-warning-700' => $key === 'with_warnings' && $preview[$key] > 0])>{{ $preview[$key] }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($preview['advisors'] > 0)
                <p class="text-sm text-ink-700">{{ trans_choice('students.import.advisors_found', $preview['advisors']) }}</p>
            @endif
            @if ($preview['unknown_columns'] !== [])
                <p class="text-sm text-ink-500">{{ __('students.import.unknown_columns', ['columns' => implode(', ', $preview['unknown_columns'])]) }}</p>
            @endif

            @if ($preview['rows'] !== [])
                <div class="max-h-[420px] overflow-auto rounded-lg border border-line">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-surface text-xs uppercase tracking-wide text-ink-500">
                            <tr>
                                <th scope="col" class="px-3 py-2 font-bold">{{ __('students.import.row') }}</th>
                                <th scope="col" class="px-3 py-2 font-bold">{{ __('students.import.student') }}</th>
                                <th scope="col" class="px-3 py-2 font-bold">{{ __('students.import.issues') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($preview['rows'] as $row)
                                <tr wire:key="row-{{ $row['line'] }}" class="align-top">
                                    <td class="px-3 py-2 font-mono tabular-nums">{{ $row['line'] }}</td>
                                    <td class="px-3 py-2">
                                        <span class="block font-semibold [overflow-wrap:anywhere]">{{ $row['name'] ?: '—' }}</span>
                                        <span class="font-mono text-xs text-ink-500">{{ $row['number'] ?? __('students.import.new_number') }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <ul class="flex flex-col gap-0.5">
                                            @foreach ($row['errors'] as $message)
                                                <li class="font-medium text-danger-700">{{ $message }}</li>
                                            @endforeach
                                            @foreach ($row['warnings'] as $message)
                                                <li class="text-warning-700">{{ $message }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-ink-500">{{ __('students.import.rows_hint') }}</p>
            @endif

            <div class="flex flex-col-reverse gap-2.5 md:flex-row md:justify-end">
                <x-ui.button variant="ghost" wire:click="restart">{{ __('students.import.restart') }}</x-ui.button>
                @if ($preview['missing_columns'] === [] && $preview['create'] + $preview['update'] > 0)
                    <x-ui.button wire:click="import" wire:loading.attr="disabled" wire:target="import">
                        <span wire:loading wire:target="import" class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                        {{ trans_choice('students.import.confirm', $preview['create'] + $preview['update']) }}
                    </x-ui.button>
                @endif
            </div>
        </x-ui.card>
    @endif

    {{-- Resultado --}}
    @if ($result !== null)
        <x-ui.card :title="__('students.import.result_title')" class="max-w-5xl">
            <ul class="flex flex-col gap-1 text-[15px]">
                <li>{{ trans_choice('students.import.result.created', $result['created']) }}</li>
                <li>{{ trans_choice('students.import.result.updated', $result['updated']) }}</li>
                @if ($result['advisors'] > 0)
                    <li>{{ trans_choice('students.import.result.advisors', $result['advisors']) }}</li>
                @endif
                @if ($result['skipped'] > 0)
                    <li class="text-danger-700">{{ trans_choice('students.import.result.skipped', $result['skipped']) }}</li>
                @endif
                @if ($result['failed'] !== [])
                    <li class="text-danger-700">{{ __('students.import.result.failed', ['lines' => implode(', ', $result['failed'])]) }}</li>
                @endif
                @if ($result['photos'] > 0)
                    <li class="text-ink-700">{{ trans_choice('students.import.result.photos', $result['photos']) }}</li>
                @endif
            </ul>
            <p class="text-sm text-ink-500">{{ __('students.import.result.no_mail') }}</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('students.index') }}" wire:navigate
                   class="inline-flex h-11 items-center justify-center rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                    {{ __('students.import.go_to_students') }}
                </a>
                <x-ui.button variant="ghost" wire:click="restart">{{ __('students.import.another') }}</x-ui.button>
            </div>
        </x-ui.card>
    @endif
</div>
