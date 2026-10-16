<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'expense_number' => 'EXP-'.fake()->unique()->numerify('9#####'),
            'category_id' => ExpenseCategory::factory(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'expense_date' => today(),
            'payment_method' => 'cash',
            'description' => fake()->sentence(),
        ];
    }
}
