{{-- Foto del teacher (privada, servida con permisos) o sus iniciales. Variables: $teacher, $size (clases de tamaño) --}}
@if ($teacher->hasPhoto())
    <img src="{{ route('teacher-photos.show', $teacher) }}?v={{ $teacher->updated_at?->timestamp }}" alt=""
         class="{{ $size ?? 'size-10' }} flex-none rounded-full object-cover ring-1 ring-line">
@else
    <span class="{{ $size ?? 'size-10' }} flex flex-none items-center justify-center rounded-full bg-primary-100 text-[13px] font-bold text-primary-800" aria-hidden="true">{{ $teacher->initials() }}</span>
@endif
