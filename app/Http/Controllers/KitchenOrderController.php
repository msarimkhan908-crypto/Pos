<?php

namespace App\Http\Controllers;

use App\Enums\KitchenStatus;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\KitchenStation;
use App\Models\SaleItemModifier;
use App\Services\KitchenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The kitchen display: tickets (KOTs) filtered by status and station, status updates,
 * and a printable ticket.
 *
 * With a station selected, each ticket shows only that station's items, its status is that
 * station's progress, and the buttons move only that station's items.
 */
class KitchenOrderController extends Controller implements HasMiddleware
{
    /** Filters on the kitchen screen; "all" = everything still open plus today's completed. */
    public const FILTERS = ['all', 'pending', 'preparing', 'ready', 'completed'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:kitchen.view', only: ['index', 'orders', 'show']),
            new Middleware('permission:kitchen.manage', only: ['updateStatus']),
        ];
    }

    public function index(): View
    {
        $stations = KitchenStation::query()->active()->ordered()->get(['id', 'name']);

        return view('kitchen.index', compact('stations'));
    }

    /**
     * JSON feed polled by the kitchen screen.
     */
    public function orders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::FILTERS)],
            'station' => ['nullable', 'integer', 'exists:kitchen_stations,id'],
        ]);
        $filter = $validated['status'] ?? 'all';
        $stationId = isset($validated['station']) ? (int) $validated['station'] : null;

        $tickets = KitchenOrder::query()
            ->where(fn (Builder $query) => $query
                ->open()
                ->orWhere(fn (Builder $query) => $query->where('status', KitchenStatus::Completed)->where('completed_at', '>=', today())))
            ->when($stationId, fn (Builder $query, int $id) => $query->whereHas('items', fn (Builder $query) => $query->where('kitchen_station_id', $id)))
            ->with(['items.saleItem.modifiers', 'items.station:id,name', 'table:id,name', 'sale:id,invoice_number,customer_name,customer_phone'])
            ->oldest()
            ->limit(200)
            ->get();

        $cards = $tickets->map(fn (KitchenOrder $order) => $this->present($order, $stationId));

        return response()->json([
            'orders' => $cards
                ->filter(fn (array $card) => $filter === 'all' || $card['status'] === $filter)
                // Open tickets oldest first, completed ones last (newest first).
                ->sortBy(fn (array $card) => $card['status'] === 'completed' ? PHP_INT_MAX - $card['completed_ts'] : $card['created_ts'])
                ->values(),
            'counts' => collect(self::FILTERS)->mapWithKeys(fn (string $f) => [
                $f => $f === 'all' ? $cards->count() : $cards->where('status', $f)->count(),
            ]),
        ]);
    }

    /**
     * Kitchen-friendly ticket for the browser print dialog (?print=1), optionally for one station.
     */
    public function show(Request $request, KitchenOrder $kitchenOrder): View
    {
        $stationId = $request->integer('station') ?: null;
        $kitchenOrder->load(['items.saleItem.modifiers', 'items.saleItem.parent', 'items.station:id,name', 'table:id,name', 'sale:id,invoice_number,customer_name,customer_phone,customer_address', 'creator:id,name']);

        return view('prints.kot', ['order' => $kitchenOrder, 'groups' => $kitchenOrder->itemsByStation($stationId)]);
    }

    public function updateStatus(Request $request, KitchenOrder $kitchenOrder, KitchenService $kitchen): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(KitchenStatus::class)],
            'station' => ['nullable', 'integer', 'exists:kitchen_stations,id'],
        ]);

        $stationId = isset($validated['station']) ? (int) $validated['station'] : null;
        $order = $kitchen->advance($kitchenOrder, KitchenStatus::from($validated['status']), $stationId);
        $order->load(['items.saleItem.modifiers', 'items.station:id,name', 'table:id,name', 'sale:id,invoice_number,customer_name,customer_phone']);

        return response()->json([
            'message' => __(':kot: :status', ['kot' => $order->kot_number, 'status' => $order->status->label()]),
            'order' => $this->present($order, $stationId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(KitchenOrder $order, ?int $stationId): array
    {
        $items = $order->items->when($stationId, fn (Collection $items) => $items->where('kitchen_station_id', $stationId));
        $status = $stationId ? KitchenService::combine($items->pluck('status')) : $order->status;

        return [
            'id' => $order->id,
            'kot_number' => $order->kot_number,
            'status' => $status->value,
            'status_label' => $status->label(),
            'order_type' => $order->order_type->value,
            'order_type_label' => $order->order_type->label(),
            'table' => $order->table?->name,
            'invoice' => $order->sale?->invoice_number,
            'customer' => $order->sale?->customer_name,
            'notes' => $order->notes,
            'created_at' => $order->created_at->toIso8601String(),
            'created_ts' => $order->created_at->getTimestamp(),
            'completed_ts' => $order->completed_at?->getTimestamp() ?? 0,
            'time' => $order->created_at->format('H:i'),
            'print_url' => route('kitchen.orders.show', ['kitchenOrder' => $order, 'station' => $stationId, 'print' => 1], false),
            'items' => $items->values()->map(fn (KitchenOrderItem $item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'name' => $item->saleItem->product_name,
                'variant' => $item->saleItem->variant_name,
                'modifiers' => $item->saleItem->modifiers->map(fn (SaleItemModifier $m) => $m->name)->all(),
                'notes' => $item->notes,
                'station' => $item->station?->name ?? __('Kitchen'),
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
            ]),
        ];
    }
}
