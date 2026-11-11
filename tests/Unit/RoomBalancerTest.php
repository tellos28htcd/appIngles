<?php

namespace Tests\Unit;

use App\Support\RoomBalancer;
use PHPUnit\Framework\TestCase;

class RoomBalancerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function item(string $room, int $weight, bool $online = false, bool $oneToOne = false, bool $movable = true, array $excluded = []): array
    {
        return ['room' => $room, 'weight' => $weight, 'online' => $online, 'one_to_one' => $oneToOne, 'movable' => $movable, 'excluded' => $excluded];
    }

    /** @return array{group: string, capacity: int, teacher_id: ?int} */
    private function room(int $teacherId, int $capacity = 5, string $group = 'physical'): array
    {
        return ['group' => $group, 'capacity' => $capacity, 'teacher_id' => $teacherId];
    }

    public function test_the_example_rooms_get_balanced_around_the_average(): void
    {
        // MUCHO 145, UN POCO MÁS 125, FÁCIL 90 (promedio 120).
        $rooms = ['a' => $this->room(1), 'b' => $this->room(2), 'c' => $this->room(3)];
        $items = [];
        foreach (['a' => [5, 40, 40, 20, 40], 'b' => [5, 40, 40, 40], 'c' => [10, 40, 40]] as $room => $weights) {
            foreach ($weights as $weight) {
                $items[] = $this->item($room, $weight);
            }
        }
        $this->assertSame(['a' => 145, 'b' => 125, 'c' => 90], RoomBalancer::loads($rooms, $items));

        foreach (RoomBalancer::rebalance($rooms, $items) as $id => $room) {
            $items[$id]['room'] = $room;
        }

        $loads = RoomBalancer::loads($rooms, $items);
        $this->assertSame(360, array_sum($loads));
        $this->assertLessThanOrEqual(15, max($loads) - min($loads));
        $this->assertGreaterThanOrEqual(110, min($loads));
    }

    public function test_hybrid_rooms_take_three_with_at_most_two_online(): void
    {
        $room = $this->room(1);

        $this->assertTrue(RoomBalancer::fits($room, [$this->item('a', 5, online: true), $this->item('a', 5, online: true), $this->item('a', 5)]));
        $this->assertFalse(RoomBalancer::fits($room, [$this->item('a', 5, online: true), $this->item('a', 5), $this->item('a', 5), $this->item('a', 5)]));
        $this->assertFalse(RoomBalancer::fits($room, array_fill(0, 3, $this->item('a', 5, online: true))));
        // Solo presencial: el cupo de Mi Instituto.
        $this->assertTrue(RoomBalancer::fits($room, array_fill(0, 5, $this->item('a', 5))));
        $this->assertFalse(RoomBalancer::fits($room, array_fill(0, 6, $this->item('a', 5))));
    }

    public function test_one_to_one_and_excluded_teachers(): void
    {
        $room = $this->room(7);

        $this->assertTrue(RoomBalancer::fits($room, [$this->item('a', 240, oneToOne: true)]));
        $this->assertFalse(RoomBalancer::fits($room, [$this->item('a', 240, oneToOne: true), $this->item('a', 5)]));
        $this->assertFalse(RoomBalancer::fits($room, [$this->item('a', 5, excluded: [7])]));
        $this->assertNull(RoomBalancer::bestRoom(['a' => $room], [$this->item('a', 5)], $this->item('', 240, oneToOne: true)));
    }

    public function test_only_movable_students_move_and_only_within_their_group(): void
    {
        $rooms = ['a' => $this->room(1), 'b' => $this->room(2), 'v' => $this->room(3, 5, 'virtual')];
        $items = [
            1 => $this->item('a', 40, movable: false),
            2 => $this->item('a', 40, excluded: [2]),
            3 => $this->item('a', 40),
            4 => $this->item('v', 40, online: true),
            5 => $this->item('v', 40, online: true),
        ];

        // Solo el 3 puede pasar a "b" (el 1 ya asistió, el 2 excluyó a ese teacher); la virtual no se mezcla.
        $this->assertSame([3 => 'b'], RoomBalancer::rebalance($rooms, $items));
    }
}
