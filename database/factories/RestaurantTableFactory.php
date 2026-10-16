<?php

namespace Database\Factories;

use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantTable>
 */
class RestaurantTableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Table '.fake()->unique()->numberBetween(1, 9999),
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
