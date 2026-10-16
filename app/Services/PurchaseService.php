<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseException;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates and changes purchases. Everything runs in one transaction, so a failure
 * part-way leaves no purchase and no items behind.
 *
 * Purchases are records only: they do not change stock (there is no inventory module).
 */
class PurchaseService
{
    public function __construct(private PurchaseCalculator $calculator) {}

    /**
     * @param  array{supplier_id: int, purchase_date: string, payment_status: string, status: string, notes?: ?string}  $data
     * @param  list<array<string, mixed>>  $items  validated rows: product_id, variant_id, quantity, purchase_price, discount, tax
     */
    public function create(array $data, array $items, ?User $user): Purchase
    {
        return DB::transaction(function () use ($data, $items, $user) {
            $purchase = new Purchase($data);
            $purchase->created_by = $user?->getKey();

            $this->fillTotalsAndSave($purchase, $items);

            // The id is unique, so a number derived from it is too (backed by a unique index).
            $purchase->forceFill(['purchase_number' => Purchase::numberFor($purchase->getKey())])->save();

            return $purchase;
        });
    }

    /**
     * Replace the details and items of a draft purchase.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     *
     * @throws PurchaseException
     */
    public function update(Purchase $purchase, array $data, array $items): Purchase
    {
        if (! $purchase->isDraft()) {
            throw PurchaseException::notEditable($purchase->status);
        }

        return DB::transaction(function () use ($purchase, $data, $items) {
            $purchase->fill($data);
            $purchase->items()->delete();
            $this->fillTotalsAndSave($purchase, $items);

            return $purchase;
        });
    }

    /**
     * @throws PurchaseException
     */
    public function changeStatus(Purchase $purchase, PurchaseStatus $status): Purchase
    {
        if (! $purchase->status->canTransitionTo($status)) {
            throw PurchaseException::invalidTransition($purchase->status, $status);
        }

        $purchase->update(['status' => $status]);

        return $purchase;
    }

    /**
     * @throws PurchaseException
     */
    public function changePaymentStatus(Purchase $purchase, PaymentStatus $status): Purchase
    {
        if ($purchase->isCancelled()) {
            throw PurchaseException::cancelled();
        }

        $purchase->update(['payment_status' => $status]);

        return $purchase;
    }

    /**
     * @throws PurchaseException
     */
    public function delete(Purchase $purchase): void
    {
        if (! $purchase->isDraft()) {
            throw PurchaseException::notDeletable();
        }

        // Items cascade via the foreign key.
        $purchase->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function fillTotalsAndSave(Purchase $purchase, array $items): void
    {
        $totals = $this->calculator->calculate($items);

        $purchase->forceFill([
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'tax' => $totals['tax'],
            'total' => $totals['total'],
        ])->save();

        foreach ($totals['items'] as $line) {
            $purchase->items()->create([
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'] ?? null,
                'quantity' => $line['quantity'],
                'purchase_price' => $line['purchase_price'],
                'discount' => $line['discount'],
                'tax' => $line['tax'],
                'line_total' => $line['line_total'],
            ]);
        }
    }
}
