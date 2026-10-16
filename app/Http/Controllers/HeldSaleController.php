<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartRequest;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Hold the current cart, list held sales, resume or discard one. Held sales never
 * get an invoice number or payments until they are completed through checkout.
 */
class HeldSaleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:sales.create'),
        ];
    }

    public function index(): JsonResponse
    {
        $held = Sale::query()
            ->held()
            ->with(['customer:id,name', 'user:id,name', 'table:id,name', 'items:id,sale_id,product_name,variant_name,quantity'])
            ->latest('id')
            ->get();

        return response()->json([
            'held' => $held->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'customer' => $sale->customerLabel(),
                'order_type' => $sale->order_type->label(),
                'table' => $sale->table?->name,
                'phone' => $sale->customer_phone,
                'delivery' => (float) $sale->delivery_fee > 0 || filled($sale->customer_address),
                'cashier' => $sale->user?->name,
                'total' => (string) $sale->total,
                'held_at' => $sale->created_at->toIso8601String(),
                'held_at_label' => $sale->created_at->diffForHumans(),
                'items' => $sale->items->map(fn (SaleItem $item) => [
                    'name' => $item->displayName(),
                    'quantity' => $item->quantity,
                ]),
            ]),
        ]);
    }

    public function store(CartRequest $request, SaleService $sales): JsonResponse
    {
        $sale = $sales->hold($request->cart(), $request->user());

        return response()->json([
            'message' => __('Sale held as #:id.', ['id' => $sale->id]),
            'id' => $sale->id,
        ], 201);
    }

    public function resume(Sale $sale, SaleService $sales): JsonResponse
    {
        abort_unless($sale->isHeld(), 404);

        $cart = $sales->resume($sale);

        return response()->json([
            ...$cart,
            'message' => __('Held sale #:id resumed.', ['id' => $sale->id]),
        ]);
    }

    public function destroy(Sale $sale, SaleService $sales): JsonResponse
    {
        abort_unless($sale->isHeld(), 404);

        $sales->discard($sale);

        return response()->json(['message' => __('Held sale #:id removed.', ['id' => $sale->id])]);
    }
}
