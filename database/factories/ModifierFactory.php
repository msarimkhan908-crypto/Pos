<?php

namespace Database\Factories;

use App\Models\Modifier;
use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Modifier>
 */
class ModifierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'modifier_group_id' => ModifierGroup::factory(),
            'name' => ucfirst(fake()->unique()->word()),
            'price' => fake()->randomElement([0, 50, 100, 150, 200]),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
