{{-- Foto del alumno (privada, servida con permisos) o sus iniciales. Variables: $student, $size (clases de tamaño) --}}
@if ($student->hasPhoto())
    <img src="{{ route('student-photos.show', $student) }}?v={{ $student->updated_at?->timestamp }}" alt=""
         class="{{ $size ?? 'size-10' }} flex-none rounded-full object-cover ring-1 ring-line">
@else
    <span class="{{ $size ?? 'size-10' }} flex flex-none items-center justify-center rounded-full bg-primary-100 text-[13px] font-bold text-primary-800" aria-hidden="true">{{ $student->initials() }}</span>
@endif
