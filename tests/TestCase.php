<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Livewire\Livewire;

abstract class TestCase extends BaseTestCase
{
    /**
     * Filtros de una pantalla ya guardados en sesión (como si el usuario los hubiera elegido antes):
     * los filtros no viajan en la URL, viven en la sesión con la clave de Livewire (#[Session]).
     *
     * @param  class-string  $component
     * @param  array<string, mixed>  $values  propiedad => valor
     */
    protected function withFilters(string $component, array $values): static
    {
        $name = Livewire::new($component)->getName();

        return $this->withSession(collect($values)->mapWithKeys(fn ($value, string $property) => ['lw'.crc32($name.$property) => $value])->all());
    }
}
