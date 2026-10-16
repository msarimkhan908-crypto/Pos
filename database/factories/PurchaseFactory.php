<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A purchase with no items and zero totals. Tests that need items should go
 * through PurchaseService so totals are calculated.
 *
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'purchase_date' => now()->toDateString(),
            'status' => 'draft',
            'payment_status' => 'unpaid',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Purchase $purchase) {
            $purchase->forceFill(['purchase_number' => Purchase::numberFor($purchase->id)])->save();
        });
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
