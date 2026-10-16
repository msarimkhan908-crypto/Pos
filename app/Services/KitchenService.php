<?php

namespace App\Services;

use App\Enums\KitchenStatus;
use App\Enums\TableStatus;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kitchen order tickets (KOTs).
 *
 * send() runs inside the checkout transaction and creates one KOT (KOT-000001, …) for the
 * sale's kitchen items: lines whose product or category has an active kitchen station.
 * Each KOT item keeps its station, so a station screen (e.g. Grill) works on its own items.
 *
 * advance() moves items forward (Pending -> Preparing -> Ready -> Completed), for the whole
 * ticket or one station. The ticket's status follows its items: Completed when all items
 * are, Ready when all are at least ready, Preparing once any has started. When a Dine In
 * ticket completes and its table has no other open tickets, the table becomes Available.
 *
 * Nothing here touches stock (there is no inventory module), so stock can't change twice.
 */
class KitchenService
{
    /**
     * @param  list<array<string, mixed>>  $lines  priced lines from CartResolver (with kitchen_station_id)
     * @param  list<SaleItem>  $items  the saved sale items, in the same order as $lines
     */
    public function send(Sale $sale, array $lines, array $items, ?User $user): ?KitchenOrder
    {
        $kitchenLines = collect($lines)
            ->map(fn (array $line, int $i) => [...$line, 'sale_item' => $items[$i]])
            ->filter(fn (array $line) => $line['kitchen_station_id'] !== null)
            // Deal parent lines carry zero components in the kitchen; their components go instead.
            ->filter(fn (array $line) => ! ($line['is_deal'] ?? false));

        if ($kitchenLines->isEmpty()) {
            return null;
        }

        // The KOT shares the sale's order_no (set during checkout) — no separate 'kot' counter.
        $orderNo = $sale->order_no ?? 0;

        $order = (new KitchenOrder)->forceFill([
            'kot_number' => KitchenOrder::numberFor($orderNo),
            'sale_id' => $sale->id,
            'business_date' => $sale->business_date,
            'order_no' => $orderNo,
            'table_id' => $sale->table_id,
            'order_type' => $sale->order_type,
            'status' => KitchenStatus::Pending,
            'notes' => $sale->order_note,
            'created_by' => $user?->getKey(),
        ]);
        $order->save();

        foreach ($kitchenLines as $line) {
            $order->items()->forceCreate([
                'sale_item_id' => $line['sale_item']->id,
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'],
                'kitchen_station_id' => $line['kitchen_station_id'],
                'quantity' => $line['quantity'],
                'notes' => $line['note'],
                'status' => KitchenStatus::Pending,
            ]);
        }

        return $order;
    }

    /**
     * Move a ticket's items (all, or one station's) forward to $status.
     *
     * @throws ValidationException
     */
    public function advance(KitchenOrder $order, KitchenStatus $status, ?int $stationId = null): KitchenOrder
    {
        if (! in_array($status, KitchenStatus::targets(), true)) {
            throw ValidationException::withMessages(['status' => __('Choose Preparing, Ready or Completed.')]);
        }

        return DB::transaction(function () use ($order, $status, $stationId) {
            $order = KitchenOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            $moving = $order->items()
                ->when($stationId, fn ($query, int $id) => $query->where('kitchen_station_id', $id))
                ->get()
                ->filter(fn (KitchenOrderItem $item) => $item->status->rank() < $status->rank());

            if ($moving->isEmpty()) {
                throw ValidationException::withMessages(['status' => __(':kot is already :status.', [
                    'kot' => $order->kot_number,
                    'status' => $status->label(),
                ])]);
            }

            KitchenOrderItem::query()->whereKey($moving->modelKeys())->update(['status' => $status, 'updated_at' => now()]);

            return $this->refresh($order);
        });
    }

    /**
     * Recalculate the ticket status from its items, stamp the times, and release the table
     * once its last open ticket is completed.
     */
    public function refresh(KitchenOrder $order): KitchenOrder
    {
        $statuses = $order->items()->pluck('status')
            ->map(fn ($status) => $status instanceof KitchenStatus ? $status : KitchenStatus::from($status));

        $status = static::combine($statuses);
        $rank = $status->rank();

        $order->forceFill([
            'status' => $status,
            'started_at' => $order->started_at ?? ($rank >= 1 ? now() : null),
            'ready_at' => $rank >= 2 ? ($order->ready_at ?? now()) : null,
            'completed_at' => $status === KitchenStatus::Completed ? ($order->completed_at ?? now()) : null,
        ])->save();

        if ($status === KitchenStatus::Completed && $order->table_id) {
            $this->releaseTable($order->table_id);
        }

        return $order;
    }

    /**
     * The status of a group of items (a whole ticket, or one station's part of it).
     *
     * @param  iterable<KitchenStatus>  $statuses
     */
    public static function combine(iterable $statuses): KitchenStatus
    {
        $ranks = collect($statuses)
            ->reject(fn (KitchenStatus $status) => $status === KitchenStatus::Cancelled)
            ->map(fn (KitchenStatus $status) => $status->rank());

        return match (true) {
            $ranks->isEmpty() => KitchenStatus::Cancelled,
            $ranks->min() >= KitchenStatus::Completed->rank() => KitchenStatus::Completed,
            $ranks->min() >= KitchenStatus::Ready->rank() => KitchenStatus::Ready,
            $ranks->max() >= KitchenStatus::Preparing->rank() => KitchenStatus::Preparing,
            default => KitchenStatus::Pending,
        };
    }

    /**
     * An Occupied table becomes Available when none of its tickets are still in the kitchen.
     */
    private function releaseTable(int $tableId): void
    {
        $stillOpen = KitchenOrder::query()->open()->where('table_id', $tableId)->exists();

        if (! $stillOpen) {
            RestaurantTable::query()
                ->whereKey($tableId)
                ->where('status', TableStatus::Occupied)
                ->update(['status' => TableStatus::Available, 'updated_at' => now()]);
        }
    }
}
