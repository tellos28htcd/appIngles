<?php

namespace App\Support;

/**
 * Balanceo de aulas de práctica a una hora (modelo por pesos): cada actividad tiene un peso y la carga de
 * un teacher es la suma de los pesos de sus alumnos. Se busca que las cargas queden parejas.
 *
 * Trabaja con arreglos simples (no consulta la base):
 * - Aula: ['group' => 'physical'|'virtual', 'capacity' => int, 'teacher_id' => ?int].
 * - Alumno (reservación): ['room' => ?string, 'weight' => int, 'online' => bool, 'one_to_one' => bool,
 *   'movable' => bool, 'excluded' => list<int>].
 *
 * Reglas de un aula:
 * - Con una actividad uno a uno (PR, presentación) el alumno va solo.
 * - Física híbrida (con alumnos en línea): máximo HYBRID_CAPACITY (sin pasar su cupo) y a lo más HYBRID_MAX_ONLINE en línea.
 * - Física solo presencial y aula virtual: su cupo.
 * - Nunca con un teacher que el alumno excluyó en su expediente.
 */
final class RoomBalancer
{
    public const HYBRID_CAPACITY = 3;

    public const HYBRID_MAX_ONLINE = 2;

    /** Vueltas máximas de la búsqueda (cada una mejora el balance o termina). */
    private const MAX_ROUNDS = 200;

    /**
     * ¿Caben juntos estos alumnos en el aula?
     *
     * @param  array{group: string, capacity: int, teacher_id: ?int}  $room
     * @param  list<array<string, mixed>>  $items
     */
    public static function fits(array $room, array $items): bool
    {
        $count = count($items);

        if ($count === 0) {
            return true;
        }

        if ($count > 1 && collect($items)->contains('one_to_one', true)) {
            return false;
        }

        foreach ($items as $item) {
            if ($room['teacher_id'] !== null && in_array($room['teacher_id'], $item['excluded'], true)) {
                return false;
            }
        }

        $online = collect($items)->where('online', true)->count();

        if ($room['group'] === 'physical' && $online > 0) {
            return $count <= min(self::HYBRID_CAPACITY, $room['capacity']) && $online <= self::HYBRID_MAX_ONLINE;
        }

        return $count <= $room['capacity'];
    }

    /**
     * Cuántos alumnos como $item caben todavía en el aula.
     *
     * @param  array{group: string, capacity: int, teacher_id: ?int}  $room
     * @param  list<array<string, mixed>>  $items  alumnos que ya están en el aula
     * @param  array<string, mixed>  $item
     */
    public static function seatsFor(array $room, array $items, array $item): int
    {
        $seats = 0;

        while ($seats < 100 && self::fits($room, [...$items, ...array_fill(0, $seats + 1, $item)])) {
            $seats++;
        }

        return $seats;
    }

    /**
     * Aula para un alumno nuevo: entre las que lo aceptan, la de menor carga (empates al azar).
     *
     * @param  array<string, array{group: string, capacity: int, teacher_id: ?int}>  $rooms
     * @param  array<int|string, array<string, mixed>>  $items
     * @param  array<string, mixed>  $item
     */
    public static function bestRoom(array $rooms, array $items, array $item): ?string
    {
        $loads = self::loads($rooms, $items);
        $candidates = [];

        foreach ($rooms as $key => $room) {
            if (self::fits($room, [...self::itemsIn($items, (string) $key), $item])) {
                $candidates[(string) $key] = $loads[$key];
            }
        }

        if ($candidates === []) {
            return null;
        }

        $lowest = min($candidates);
        $ties = array_keys(array_filter($candidates, fn (int $load) => $load === $lowest));

        return $ties[array_rand($ties)];
    }

    /**
     * Reacomoda a los alumnos que se pueden mover para que las cargas de cada grupo (físicas e híbridas;
     * virtuales) queden lo más parejas posible: mueve o intercambia alumnos mientras baje la suma de los
     * cuadrados de las cargas.
     *
     * @param  array<string, array{group: string, capacity: int, teacher_id: ?int}>  $rooms
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array<int|string, string> alumno => aula nueva (solo los que cambian)
     */
    public static function rebalance(array $rooms, array $items): array
    {
        $original = array_map(fn (array $item) => $item['room'], $items);

        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $best = self::bestMove($rooms, $items);

            if ($best === null) {
                break;
            }

            foreach ($best as $id => $room) {
                $items[$id]['room'] = $room;
            }
        }

        $changes = [];
        foreach ($items as $id => $item) {
            if ($item['room'] !== $original[$id]) {
                $changes[$id] = $item['room'];
            }
        }

        return $changes;
    }

    /**
     * Carga (suma de pesos) por aula.
     *
     * @param  array<string, array<string, mixed>>  $rooms
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array<string, int>
     */
    public static function loads(array $rooms, array $items): array
    {
        $loads = array_fill_keys(array_map('strval', array_keys($rooms)), 0);

        foreach ($items as $item) {
            if ($item['room'] !== null && isset($loads[$item['room']])) {
                $loads[$item['room']] += $item['weight'];
            }
        }

        return $loads;
    }

    /**
     * Mejor movimiento (mover un alumno o intercambiar dos) que baja el desbalance; null si ya no hay.
     *
     * @param  array<string, array<string, mixed>>  $rooms
     * @param  array<int|string, array<string, mixed>>  $items
     * @return array<int|string, string>|null
     */
    private static function bestMove(array $rooms, array $items): ?array
    {
        $loads = self::loads($rooms, $items);
        $best = null;
        $bestGain = 0;

        foreach ($items as $id => $item) {
            if (! $item['movable'] || $item['room'] === null || ! isset($rooms[$item['room']])) {
                continue;
            }

            $from = $item['room'];

            foreach ($rooms as $to => $room) {
                $to = (string) $to;

                if ($to === $from || $room['group'] !== $rooms[$from]['group']) {
                    continue;
                }

                // Mover el alumno a otra aula.
                $gain = self::gain($loads[$from], $loads[$to], $item['weight'], 0);
                if ($gain > $bestGain && self::fits($room, [...self::itemsIn($items, $to), $item])) {
                    [$best, $bestGain] = [[$id => $to], $gain];
                }

                // Intercambiarlo con un alumno de esa aula.
                foreach ($items as $otherId => $other) {
                    if ($other['room'] !== $to || ! $other['movable']) {
                        continue;
                    }

                    $gain = self::gain($loads[$from], $loads[$to], $item['weight'], $other['weight']);
                    if ($gain <= $bestGain) {
                        continue;
                    }

                    $fromAfter = [...self::itemsIn($items, $from, except: $id), $other];
                    $toAfter = [...self::itemsIn($items, $to, except: $otherId), $item];

                    if (self::fits($rooms[$from], $fromAfter) && self::fits($room, $toAfter)) {
                        [$best, $bestGain] = [[$id => $to, $otherId => $from], $gain];
                    }
                }
            }
        }

        return $best;
    }

    /** Cuánto baja la suma de cuadrados al pasar $moved de "from" a "to" y $back de "to" a "from". */
    private static function gain(int $fromLoad, int $toLoad, int $moved, int $back): int
    {
        $before = $fromLoad ** 2 + $toLoad ** 2;
        $after = ($fromLoad - $moved + $back) ** 2 + ($toLoad + $moved - $back) ** 2;

        return $before - $after;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function itemsIn(array $items, string $room, int|string|null $except = null): array
    {
        $result = [];
        foreach ($items as $id => $item) {
            if ($item['room'] === $room && $id !== $except) {
                $result[] = $item;
            }
        }

        return $result;
    }
}
