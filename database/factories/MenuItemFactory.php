<?php

namespace Database\Factories;

use App\Enums\MenuItemStatus;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'slug' => fake()->unique()->slug(2),
            'label' => fake()->words(2, true),
            'icon' => 'home',
            'route_name' => null,
            'status' => MenuItemStatus::Active,
            'sort_order' => 0,
        ];
    }

    public function comingSoon(): static
    {
        return $this->state(fn () => ['status' => MenuItemStatus::ComingSoon, 'route_name' => null]);
    }
}
