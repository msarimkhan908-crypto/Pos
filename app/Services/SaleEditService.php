<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\KitchenStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\TableStatus;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleEdit;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edits a completed bill.
 *
 * - order_no, business_date and invoice_number never change.
 * - Existing line prices and tax rates are kept as snapshots.
 * - Only newly added lines use current catalog prices.
 * - An audit row is written to sale_edits.
 * - KOT is updated only when all its items are still Pending.
 */
class SaleEditService
{
    public function __construct(
        private SaleService $sales,
        private SaleCalculator $calculator,
        private CartResolver $resolver,
    ) {}

    /**
     * Validate that a sale may be edited by the given user.
     *
     * @throws ValidationException
     */
    public function assertEditable(Sale $sale): void
    {
        if ($sale->status !== SaleStatus::Completed) {
            throw ValidationException::withMessages(['sale' => __('Only completed sales can be edited.')]);
        }

        if ($sale->return_status !== SaleReturnStatus::None) {
            throw ValidationException::withMessages(['sale' => __('Returned bills cannot be edited.')]);
        }
    }

    /**
     * Apply the edit inside a transaction.
     *
     * @param  array<string, mixed>  $data  Validated fields from the edit request
     * @return array{sale: Sale, kotWarning: ?string}
     *
     * @throws ValidationException
     */
    public function edit(Sale $sale, array $data, ?User $editor): array
    {
        return DB::transaction(function () use ($sale, $data, $editor) {
            // Row-lock the sale so two simultaneous edits don't race.
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            $this->assertEditable($sale);

            $before = $this->snapshot($sale);

            // Resolve the new items list.
            // Existing lines: keep unit_price and tax_rate from snapshot.
            // New lines: price from catalog.
            $resolvedLines = $this->resolveLines($sale, $data['items'] ?? []);

            // Re-run the calculator with the (possibly changed) order discount.
            $orderDiscountType = DiscountType::tryFrom((string) ($data['order_discount_type'] ?? ''));
            $orderDiscountValue = (string) ($data['order_discount_value'] ?? '0');
            $quote = $this->calculator->calculate($resolvedLines, $orderDiscountType, $orderDiscountValue);

            $deliveryFee = bcadd((string) ($data['delivery_fee'] ?? '0'), '0', 2);
            $newTotal = bcadd($quote['total'], $deliveryFee, 2);

            // Keep original payment method and update amounts to the new total without re-validating.
            // The original cash collected is a historical fact; we update the payment record to match.
            $method = $sale->payment_method ?? PaymentMethod::Cash;
            $settled = match (true) {
                $method === PaymentMethod::Card || $method->isWallet() => ['method' => $method, 'cash_received' => null, 'change' => '0.00', 'payments' => [$method->value => $newTotal]],
                $method === PaymentMethod::Mixed => ['method' => $method, 'cash_received' => $newTotal, 'change' => '0.00', 'payments' => ['cash' => $newTotal]],
                default => ['method' => $method, 'cash_received' => $newTotal, 'change' => '0.00', 'payments' => ['cash' => $newTotal]],
            };

            // Determine new order type / table.
            $orderType = OrderType::tryFrom((string) ($data['order_type'] ?? $sale->order_type->value)) ?? $sale->order_type;
            $tableId = $orderType === OrderType::DineIn ? ($data['table_id'] ?? $sale->table_id) : null;

            // Check KOT statuses now, before items are deleted (cascade delete wipes KOT items).
            [$kotWarning, $kotsToRebuild] = $this->checkKots($sale);

            // When KOT is already in-progress we still edit the bill but must preserve KOT items.
            // The FK on kot_items.sale_item_id uses CASCADE ON DELETE, so deleting sale items
            // would wipe the KOT. Snapshot and manually remove them first; restore after.
            $kotItemsToRestore = null;
            if ($kotWarning !== null) {
                $kotItemsToRestore = $this->snapshotKotItems($sale);
                KitchenOrderItem::query()
                    ->whereHas('kitchenOrder', fn ($q) => $q->where('sale_id', $sale->id))
                    ->delete();
            }

            // Update the sale row.
            $sale->forceFill([
                'customer_name' => $data['customer_name'] ?? $sale->customer_name,
                'customer_phone' => $data['customer_phone'] ?? $sale->customer_phone,
                'customer_address' => $data['customer_address'] ?? $sale->customer_address,
                'notes' => $data['notes'] ?? $sale->notes,
                'order_type' => $orderType,
                'table_id' => $tableId,
                'delivery_fee' => $deliveryFee,
                'subtotal' => $quote['subtotal'],
                'item_discount' => $quote['item_discount'],
                'order_discount_type' => $orderDiscountType,
                'order_discount_value' => $orderDiscountType ? bcadd($orderDiscountValue, '0', 2) : '0.00',
                'order_discount' => $quote['order_discount'],
                'discount' => $quote['discount'],
                'tax' => $quote['tax'],
                'total' => $newTotal,
                'payment_method' => $settled['method'],
                'cash_received' => $settled['cash_received'],
                'change_amount' => $settled['change'],
                'edit_version' => $sale->edit_version + 1,
            ])->save();

            // Replace sale items.
            $sale->items()->delete();

            $savedItems = [];
            foreach ($quote['lines'] as $line) {
                $item = $sale->items()->forceCreate([
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'],
                    'product_name' => $line['product_name'],
                    'variant_name' => $line['variant_name'],
                    'sku' => $line['sku'],
                    'note' => $line['note'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'modifiers_total' => $line['modifiers_total'],
                    'discount_type' => $line['discount'] !== '0.00' ? $line['discount_type'] : null,
                    'discount_value' => $line['discount'] !== '0.00' ? bcadd($line['discount_value'], '0', 2) : '0.00',
                    'discount' => $line['discount'],
                    'order_discount' => $line['order_discount'],
                    'tax_rate' => $line['tax_rate'],
                    'tax' => $line['tax'],
                    'total' => $line['total'],
                ]);

                foreach ($line['modifiers'] as $modifier) {
                    $item->modifiers()->forceCreate($modifier);
                }

                $savedItems[] = $item;
            }

            // Replace payments.
            $sale->payments()->delete();
            foreach ($settled['payments'] as $method => $amount) {
                $sale->payments()->forceCreate(['payment_method' => $method, 'amount' => $amount]);
            }

            // Table status: update if order type changed.
            $this->syncTable($sale, $before, $orderType, $tableId);

            // KOT: rebuild only if all items were still Pending (checked earlier, before item delete).
            if ($kotsToRebuild->isNotEmpty()) {
                $this->rebuildKots($kotsToRebuild, $savedItems);
            }

            // Restore in-progress KOT items (snapshot taken before cascade delete).
            if ($kotItemsToRestore !== null) {
                $this->restoreKotItems($kotItemsToRestore, $savedItems);
            }

            // Audit row.
            SaleEdit::forceCreate([
                'sale_id' => $sale->id,
                'user_id' => $editor?->getKey(),
                'reason' => $data['reason'],
                'before' => $before,
                'after' => $this->snapshot($sale->fresh()),
            ]);

            $sale->refresh();

            return ['sale' => $sale, 'kotWarning' => $kotWarning];
        });
    }

    /**
     * Resolve the lines for the edit.
     * Existing lines keep their snapshot price; new lines use current catalog prices.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function resolveLines(Sale $sale, array $items): array
    {
        $existing = $sale->items->keyBy('id');
        $lines = [];

        foreach ($items as $item) {
            $existingId = $item['existing_id'] ?? null;

            if ($existingId && $existing->has((int) $existingId)) {
                /** @var SaleItem $orig */
                $orig = $existing->get((int) $existingId);

                // Build a synthetic line using the snapshot price and tax rate.
                $quantity = max(1, (int) ($item['quantity'] ?? $orig->quantity));
                $discountType = DiscountType::tryFrom((string) ($item['discount_type'] ?? $orig->discount_type?->value ?? ''));
                $discountValue = (string) ($item['discount_value'] ?? $orig->discount_value ?? '0');

                $lines[] = [
                    'product_id' => $orig->product_id,
                    'variant_id' => $orig->variant_id,
                    'product_name' => $orig->product_name,
                    'variant_name' => $orig->variant_name,
                    'sku' => $orig->sku,
                    'note' => $item['note'] ?? $orig->note,
                    'unit_price' => (string) $orig->unit_price,
                    'modifiers_total' => (string) $orig->modifiers_total,
                    'quantity' => $quantity,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'tax_rate' => (string) $orig->tax_rate,
                    'kitchen_station_id' => null, // resolved later if needed
                    'modifiers' => $orig->modifiers->map(fn ($m) => [
                        'modifier_id' => $m->modifier_id,
                        'group_name' => $m->group_name,
                        'name' => $m->name,
                        'price' => (string) $m->price,
                    ])->all(),
                ];
            } else {
                // New line: price from catalog.
                $newLine = $this->resolver->resolve([$item]);
                $lines[] = $newLine[0];
            }
        }

        return $lines;
    }

    /**
     * Keep table statuses consistent when order type or table changes.
     *
     * @param  array<string, mixed>  $before
     */
    private function syncTable(Sale $sale, array $before, OrderType $newOrderType, ?int $newTableId): void
    {
        $oldType = $before['order_type'] ?? null;
        $oldTableId = $before['table_id'] ?? null;

        // Release old table if it changed.
        if ($oldType === OrderType::DineIn->value && $oldTableId && $oldTableId !== $newTableId) {
            $openKots = KitchenOrder::query()->open()->where('table_id', $oldTableId)->exists();
            if (! $openKots) {
                RestaurantTable::query()
                    ->whereKey($oldTableId)
                    ->where('status', TableStatus::Occupied)
                    ->update(['status' => TableStatus::Available, 'updated_at' => now()]);
            }
        }

        // Occupy new table.
        if ($newOrderType === OrderType::DineIn && $newTableId) {
            RestaurantTable::query()
                ->whereKey($newTableId)
                ->where('status', TableStatus::Available)
                ->update(['status' => TableStatus::Occupied, 'updated_at' => now()]);
        }
    }

    /**
     * Load KOTs for this sale and decide whether they can be rebuilt.
     *
     * Must be called BEFORE $sale->items()->delete() because the FK on kot_items
     * uses cascadeOnDelete, which would erase all KOT items when sale items are deleted,
     * making the status check impossible afterwards.
     *
     * @return array{0: ?string, 1: Collection<int, KitchenOrder>}
     */
    private function checkKots(Sale $sale): array
    {
        $kots = KitchenOrder::query()->where('sale_id', $sale->id)->with('items')->get();

        if ($kots->isEmpty()) {
            return [null, collect()];
        }

        foreach ($kots as $kot) {
            $allPending = $kot->items->every(
                fn ($item) => $item->status === KitchenStatus::Pending
            );

            if (! $allPending) {
                return [__('Kitchen already started, KOT not changed.'), collect()];
            }
        }

        // All KOTs have only Pending items — safe to rebuild.
        return [null, $kots];
    }

    /**
     * Rebuild KOT items after the sale items have been replaced.
     * KOT items were already cascade-deleted when sale items were removed.
     *
     * @param  Collection<int, KitchenOrder>  $kots
     * @param  list<SaleItem>  $newItems
     */
    private function rebuildKots(Collection $kots, array $newItems): void
    {
        foreach ($kots as $kot) {
            foreach ($newItems as $saleItem) {
                if ($saleItem->product->kitchen_station_id) {
                    $kot->items()->forceCreate([
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        'variant_id' => $saleItem->variant_id,
                        'kitchen_station_id' => $saleItem->product->kitchen_station_id,
                        'quantity' => $saleItem->quantity,
                        'notes' => $saleItem->note,
                        'status' => KitchenStatus::Pending,
                    ]);
                }
            }
        }
    }

    /**
     * Save the raw attributes of every KOT item for this sale so they can be restored
     * after the cascade delete triggered by $sale->items()->delete().
     *
     * @return list<array<string, mixed>>
     */
    private function snapshotKotItems(Sale $sale): array
    {
        return KitchenOrderItem::query()
            ->whereHas('kitchenOrder', fn ($q) => $q->where('sale_id', $sale->id))
            ->get()
            ->map(fn ($item) => [
                'kitchen_order_id' => $item->kitchen_order_id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'kitchen_station_id' => $item->kitchen_station_id,
                'quantity' => $item->quantity,
                'notes' => $item->notes,
                'status' => $item->status,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ])
            ->all();
    }

    /**
     * Re-create KOT items from a snapshot, linking them to new sale items by product+variant.
     *
     * @param  list<array<string, mixed>>  $snapshot
     * @param  list<SaleItem>  $newItems
     */
    private function restoreKotItems(array $snapshot, array $newItems): void
    {
        // Build a lookup: [product_id:variant_id => new SaleItem]
        $lookup = [];
        foreach ($newItems as $item) {
            $key = $item->product_id.':'.($item->variant_id ?? '');
            $lookup[$key] = $item;
        }

        foreach ($snapshot as $row) {
            $key = $row['product_id'].':'.($row['variant_id'] ?? '');
            $newSaleItemId = $lookup[$key]?->id ?? null;

            if ($newSaleItemId === null) {
                // The product was removed in this edit — skip this KOT item.
                continue;
            }

            KitchenOrderItem::forceCreate([
                'kitchen_order_id' => $row['kitchen_order_id'],
                'sale_item_id' => $newSaleItemId,
                'product_id' => $row['product_id'],
                'variant_id' => $row['variant_id'],
                'kitchen_station_id' => $row['kitchen_station_id'],
                'quantity' => $row['quantity'],
                'notes' => $row['notes'],
                'status' => $row['status'],
            ]);
        }
    }

    /**
     * Snapshot of the sale's key fields for the audit log.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Sale $sale): array
    {
        return [
            'customer_name' => $sale->customer_name,
            'customer_phone' => $sale->customer_phone,
            'customer_address' => $sale->customer_address,
            'notes' => $sale->notes,
            'order_type' => $sale->order_type?->value,
            'table_id' => $sale->table_id,
            'delivery_fee' => (string) $sale->delivery_fee,
            'subtotal' => (string) $sale->subtotal,
            'discount' => (string) $sale->discount,
            'tax' => (string) $sale->tax,
            'total' => (string) $sale->total,
            'payment_method' => $sale->payment_method?->value,
        ];
    }
}
