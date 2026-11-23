<?php

namespace App\Support;

/**
 * Balanceo de aulas de práctica a una hora (modelo por pesos): cada actividad tiene un peso y la carga de
 * un teacher es la suma de los pesos de sus alumnos. Se busca que las cargas queden parejas.
 *
 * Trabaja con arreglos simples (no consulta la base):
 * - Aula: ['group' => 'physical'|'virtual', 'capacity' => int, 'teacher_id' => ?int].
 * - Alumno (reservación): ['room' => ?string, 'weight' => int, 'online' => bool,
 *   'movable' => bool, 'excluded' => list<int>].
 *
 * Reglas de un aula:
 * - Física híbrida (con alumnos en línea): máximo HYBRID_CAPACITY (sin pasar su cupo) y a lo más HYBRID_MAX_ONLINE en línea.
 * - Física solo presencial y aula virtual: su cupo.
 * - Nunca con un teacher que el alumno excluyó en su expediente.
 * - Con un alumno 'exclusive' (presentación 1 a 1), nadie más.
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

        foreach ($items as $item) {
            if ($room['teacher_id'] !== null && in_array($room['teacher_id'], $item['excluded'], true)) {
                return false;
            }
            // Presentación 1 a 1: el aula queda solo para ese alumno.
            if (($item['exclusive'] ?? false) && $count > 1) {
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

        if ($item['online']) {
            $candidates = self::hybridCandidates($rooms, $items, $candidates);
        }

        $lowest = min($candidates);
        $ties = array_keys(array_filter($candidates, fn (int $load) => $load === $lowest));

        return $ties[array_rand($ties)];
    }

    /**
     * Un alumno en línea en aula física va a un aula que ya es híbrida (para juntar a los de línea y dejar las
     * demás con su cupo presencial completo); si ninguna lo es, abre como híbrida la que tenga menos alumnos.
     *
     * @param  array<string, array{group: string, capacity: int, teacher_id: ?int}>  $rooms
     * @param  array<int|string, array<string, mixed>>  $items
     * @param  array<string, int>  $candidates  aula => carga
     * @return array<string, int>
     */
    private static function hybridCandidates(array $rooms, array $items, array $candidates): array
    {
        $physical = array_filter($candidates, fn (int $load, string $key) => $rooms[$key]['group'] === 'physical', ARRAY_FILTER_USE_BOTH);

        if ($physical === []) {
            return $candidates;
        }

        $hybrid = array_filter($physical, fn (int $load, string $key) => self::isHybrid($rooms[$key], self::itemsIn($items, $key)), ARRAY_FILTER_USE_BOTH);

        if ($hybrid !== []) {
            return $hybrid;
        }

        $counts = array_map(fn (string $key) => count(self::itemsIn($items, $key)), array_combine(array_keys($physical), array_keys($physical)));
        $fewest = min($counts);

        return array_filter($physical, fn (int $load, string $key) => $counts[$key] === $fewest, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Aula física con alumnos en línea.
     *
     * @param  array{group: string, capacity: int, teacher_id: ?int}  $room
     * @param  list<array<string, mixed>>  $items
     */
    private static function isHybrid(array $room, array $items): bool
    {
        return $room['group'] === 'physical' && collect($items)->contains('online', true);
    }

    /**
     * Reacomoda a los alumnos que se pueden mover: primero junta a los de línea en el menor número de aulas
     * híbridas (así las demás aulas físicas conservan su cupo presencial) y luego deja las cargas de cada
     * grupo (físicas e híbridas; virtuales) lo más parejas posible. Mueve o intercambia alumnos mientras baje
     * el número de aulas híbridas o, sin subirlo, la suma de los cuadrados de las cargas.
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
        $bestScore = [0, 0];

        foreach ($items as $id => $item) {
            if (! $item['movable'] || $item['room'] === null || ! isset($rooms[$item['room']])) {
                continue;
            }

            $from = $item['room'];
            $fromItems = self::itemsIn($items, $from);

            foreach ($rooms as $to => $room) {
                $to = (string) $to;

                if ($to === $from || $room['group'] !== $rooms[$from]['group']) {
                    continue;
                }

                $toItems = self::itemsIn($items, $to);
                $hybridBefore = (int) self::isHybrid($rooms[$from], $fromItems) + (int) self::isHybrid($room, $toItems);

                // Mover el alumno a otra aula.
                $fromAfter = self::itemsIn($items, $from, except: $id);
                $toAfter = [...$toItems, $item];
                $score = self::score($rooms[$from], $room, $fromAfter, $toAfter, $hybridBefore,
                    self::gain($loads[$from], $loads[$to], $item['weight'], 0));
                if (self::better($score, $bestScore) && self::fits($room, $toAfter)) {
                    [$best, $bestScore] = [[$id => $to], $score];
                }

                // Intercambiarlo con un alumno de esa aula.
                foreach ($items as $otherId => $other) {
                    if ($other['room'] !== $to || ! $other['movable']) {
                        continue;
                    }

                    $fromAfter = [...self::itemsIn($items, $from, except: $id), $other];
                    $toAfter = [...self::itemsIn($items, $to, except: $otherId), $item];
                    $score = self::score($rooms[$from], $room, $fromAfter, $toAfter, $hybridBefore,
                        self::gain($loads[$from], $loads[$to], $item['weight'], $other['weight']));

                    if (self::better($score, $bestScore) && self::fits($rooms[$from], $fromAfter) && self::fits($room, $toAfter)) {
                        [$best, $bestScore] = [[$id => $to, $otherId => $from], $score];
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Valor de un movimiento: [aulas híbridas que se quitan, baja de la suma de cuadrados]. Nunca se acepta uno
     * que abra más aulas híbridas.
     *
     * @param  array<string, mixed>  $fromRoom
     * @param  array<string, mixed>  $toRoom
     * @param  list<array<string, mixed>>  $fromAfter
     * @param  list<array<string, mixed>>  $toAfter
     * @return array{int, int}
     */
    private static function score(array $fromRoom, array $toRoom, array $fromAfter, array $toAfter, int $hybridBefore, int $gain): array
    {
        $hybridAfter = (int) self::isHybrid($fromRoom, $fromAfter) + (int) self::isHybrid($toRoom, $toAfter);

        return [$hybridBefore - $hybridAfter, $gain];
    }

    /**
     * ¿El movimiento mejora (primero menos aulas híbridas, luego cargas más parejas) y supera al mejor hasta ahora?
     *
     * @param  array{int, int}  $score
     * @param  array{int, int}  $bestScore
     */
    private static function better(array $score, array $bestScore): bool
    {
        if ($score[0] < 0 || ($score[0] === 0 && $score[1] <= 0)) {
            return false;
        }

        return $score[0] > $bestScore[0] || ($score[0] === $bestScore[0] && $score[1] > $bestScore[1]);
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
