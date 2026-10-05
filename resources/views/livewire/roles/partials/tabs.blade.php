{{-- Pestañas del módulo Roles y permisos. --}}
<nav class="flex gap-1 border-b border-line" aria-label="{{ __('roles.title') }}">
    @foreach (['roles.index' => __('roles.tabs.roles'), 'roles.menu' => __('roles.tabs.menu')] as $route => $label)
        @php($active = request()->routeIs($route) || ($route === 'roles.index' && request()->routeIs('roles.create', 'roles.edit')))
        <a href="{{ route($route) }}" wire:navigate @if ($active) aria-current="page" @endif
           @class([
               '-mb-px inline-flex h-11 items-center border-b-2 px-4 text-[15px] font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200',
               'border-primary-600 text-primary-700' => $active,
               'border-transparent text-ink-500 hover:text-ink-900' => ! $active,
           ])>{{ $label }}</a>
    @endforeach
</nav>
