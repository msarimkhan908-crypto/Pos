<?php

namespace Database\Factories;

use App\Models\KitchenStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitchenStation>
 */
class KitchenStationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Station',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
