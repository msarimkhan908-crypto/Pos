<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 1, 200);

        return [
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'purchase_price' => $cost,
            'selling_price' => round($cost * 1.4, 2),
            'tax_rate' => 0,
            'discount_value' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
