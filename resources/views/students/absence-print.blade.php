@php($date = fn ($value) => $value?->translatedFormat('j \d\e F \d\e Y') ?? '—')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('students.absences.document_title') }} · {{ $student->enrollment_number }}</title>
    @include('layouts.partials.brand', ['school' => $brand])
    @vite(['resources/css/app.css'])
    <style nonce="{{ Vite::cspNonce() }}">@media print { .no-print { display: none !important; } @page { margin: 18mm; } body { background: #fff; } }</style>
</head>
<body class="bg-surface text-ink-900">
    <div class="no-print mx-auto flex max-w-3xl justify-end gap-2 px-4 pt-6">
        <button type="button" id="print-button"
                class="inline-flex h-11 items-center rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary hover:bg-primary-700">
            {{ __('students.absences.print') }}
        </button>
    </div>

    <main class="mx-auto my-6 max-w-3xl rounded-xl bg-white p-10 shadow-xs print:my-0 print:max-w-none print:p-0 print:shadow-none">
        <header class="flex items-center justify-between gap-6 border-b border-line pb-6">
            <div class="flex items-center gap-4">
                <x-ui.brand-mark :brand="$brand" size="lg" />
                <div>
                    <p class="font-display text-xl font-extrabold">{{ $student->school->name }}</p>
                    <p class="text-sm text-ink-500">{{ __('students.absences.document_subtitle') }}</p>
                </div>
            </div>
            <p class="text-right text-sm text-ink-500">{{ __('students.absences.document_date', ['date' => $date(now(config('appingles.timezone')))]) }}</p>
        </header>

        <h1 class="mt-8 text-center font-display text-2xl font-extrabold uppercase tracking-wide">{{ __('students.absences.document_title') }}</h1>

        <dl class="mt-8 grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-2 text-[15px]">
            <dt class="font-semibold">{{ __('students.absences.document_student') }}</dt>
            <dd>{{ $student->fullName() }}</dd>
            <dt class="font-semibold">{{ __('students.fields.enrollment_number') }}</dt>
            <dd class="font-mono">{{ $student->enrollment_number }}</dd>
            <dt class="font-semibold">{{ __('students.absences.document_period') }}</dt>
            <dd>{{ __('students.absences.document_period_value', ['from' => $date($absence->starts_on), 'to' => $date($absence->ends_on)]) }}</dd>
            <dt class="font-semibold">{{ __('students.absences.document_days') }}</dt>
            <dd class="font-bold">{{ trans_choice('students.absences.days', $absence->days()) }}</dd>
            @if ($absence->reason)
                <dt class="font-semibold">{{ __('students.absences.reason') }}</dt>
                <dd>{{ $absence->reason }}</dd>
            @endif
            <dt class="font-semibold">{{ __('students.absences.document_used') }}</dt>
            <dd>{{ __('students.absences.document_used_value', ['used' => $used, 'max' => \App\Models\Student::MAX_ABSENCE_DAYS]) }}</dd>
            <dt class="font-semibold">{{ __('students.absences.document_left') }}</dt>
            <dd>{{ trans_choice('students.absences.days', $left) }}</dd>
            <dt class="font-semibold">{{ __('students.fields.course_ends_on') }}</dt>
            <dd>{{ $date($student->courseEndsOn()) }}</dd>
        </dl>

        <p class="mt-8 text-sm leading-relaxed text-ink-700">{{ __('students.absences.document_body', ['max' => \App\Models\Student::MAX_ABSENCE_DAYS]) }}</p>

        <div class="mt-24 grid grid-cols-2 gap-16 text-center text-sm">
            <div class="border-t border-ink-900 pt-2">{{ __('students.absences.document_sign_student') }}<br><span class="text-ink-500">{{ $student->fullName() }}</span></div>
            <div class="border-t border-ink-900 pt-2">{{ __('students.absences.document_sign_school') }}<br><span class="text-ink-500">{{ $student->school->name }}</span></div>
        </div>
    </main>

    <script nonce="{{ Vite::cspNonce() }}">
        document.getElementById('print-button').addEventListener('click', () => window.print());
    </script>
</body>
</html>
