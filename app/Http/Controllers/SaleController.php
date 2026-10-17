<?php

namespace App\Http\Controllers;

use App\Enums\SaleStatus;
use App\Http\Requests\CheckoutRequest;
use App\Models\Sale;
use App\Services\SaleEditService;
use App\Services\SaleService;
use App\Support\Printing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:sales.create', only: ['store']),
            new Middleware('permission:sales.create|sales.view', only: ['receipt', 'print']),
            new Middleware('permission:sales.edit', only: ['edit', 'update', 'cancel']),
        ];
    }

    /**
     * Checkout: everything is recalculated and saved in one transaction by SaleService.
     */
    public function store(CheckoutRequest $request, SaleService $sales): JsonResponse
    {
        $sale = $sales->checkout($request->cart(), $request->payment(), $request->user());
        $sale = $this->loadForReceipt($sale);

        return response()->json([
            'message' => __('Sale :invoice completed.', ['invoice' => $sale->invoice_number]),
            'sale' => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'order_no' => $sale->order_no,
                'total' => (string) $sale->total,
                'change_amount' => (string) $sale->change_amount,
                'order_type' => $sale->order_type->label(),
                'table' => $sale->table?->name,
            ],
            'kitchen_orders' => $sale->kitchenOrders->map(fn ($kot) => [
                'kot_number' => $kot->kot_number,
                'items' => $kot->items->count(),
                'print_url' => route('kitchen.orders.show', ['kitchenOrder' => $kot, 'print' => 1], false),
            ]),
            'receipt_html' => view('prints.partials.receipt', ['sale' => $sale, ...Printing::receipt()])->render(),
            'receipt_url' => route('sales.receipt', $sale, false),
            'print_url' => route('sales.print', $sale, false),
        ], 201);
    }

    /**
     * Basic printable receipt for a completed sale.
     */
    public function receipt(Sale $sale): View
    {
        abort_unless($sale->isCompleted(), 404);

        return view('prints.receipt', ['sale' => $this->loadForReceipt($sale)]);
    }

    /**
     * One print job for a placed order: the customer bill, then each kitchen order ticket on
     * its own page (a thermal printer cuts between pages). ?only=receipt or ?only=kitchen
     * prints just one of them.
     */
    public function print(Request $request, Sale $sale): View
    {
        abort_unless($sale->isCompleted(), 404);

        $only = $request->string('only')->toString();
        $sale = $this->loadForReceipt($sale)->load([
            'kitchenOrders.items.saleItem.modifiers',
            'kitchenOrders.items.station:id,name',
            'kitchenOrders.table:id,name',
            'kitchenOrders.creator:id,name',
        ]);

        return view('prints.order', [
            'sale' => $sale,
            'withReceipt' => $only !== 'kitchen',
            'kitchenOrders' => $only === 'receipt' ? collect() : $sale->kitchenOrders,
        ]);
    }

    /**
     * Edit form for a completed sale.
     */
    public function edit(Sale $sale, SaleEditService $editService): View
    {
        try {
            $editService->assertEditable($sale);
        } catch (ValidationException $e) {
            abort(403, $e->getMessage());
        }

        $sale = $this->loadForReceipt($sale)->load('edits.editor');

        return view('sales.edit', ['sale' => $sale]);
    }

    /**
     * Persist changes to a completed sale.
     */
    public function update(Request $request, Sale $sale, SaleEditService $editService): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'order_type' => ['nullable', 'string'],
            'table_id' => ['nullable', 'integer'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'order_discount_type' => ['nullable', 'string', 'in:fixed,percent'],
            'order_discount_value' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.existing_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.discount_type' => ['nullable', 'string'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
            'items.*.modifier_ids' => ['nullable', 'array'],
            'payment' => ['nullable', 'array'],
            'payment.payment_method' => ['nullable', 'string'],
            'payment.cash_received' => ['nullable', 'string'],
            'payment.cash_amount' => ['nullable', 'string'],
            'payment.card_amount' => ['nullable', 'string'],
        ]);

        // Default items to the existing sale items when not provided.
        if (empty($data['items'])) {
            $sale->load('items.modifiers');
            $data['items'] = $sale->items->map(fn ($item) => [
                'existing_id' => $item->id,
                'quantity' => $item->quantity,
                'discount_type' => $item->discount_type?->value,
                'discount_value' => (string) $item->discount_value,
                'note' => $item->note,
            ])->all();
        }

        // Default payment to the existing payment when not provided.
        if (empty($data['payment'])) {
            $sale->load('payments');
            $data['payment'] = [
                'payment_method' => $sale->payment_method?->value ?? 'cash',
                'cash_received' => (string) $sale->cash_received,
                'cash_amount' => $sale->payment_method?->value === 'mixed' ? (string) $sale->cash_received : null,
                'card_amount' => $sale->payment_method?->value === 'mixed'
                    ? (string) ($sale->payments->where('payment_method', 'card')->first()?->amount ?? 0)
                    : null,
            ];
        }

        ['sale' => $editedSale, 'kotWarning' => $kotWarning] = $editService->edit($sale, $data, $request->user());

        $redirect = redirect()->route('sales.receipt', $editedSale)
            ->with('success', __('Sale :invoice updated.', ['invoice' => $editedSale->invoice_number]));

        if ($kotWarning) {
            $redirect->with('warning', $kotWarning);
        }

        return $redirect;
    }

    /**
     * Cancel a completed sale.
     */
    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($sale->isCompleted(), 403, __('Only completed sales can be cancelled.'));

        $sale->status = SaleStatus::Cancelled;
        $sale->save();

        return redirect()->route('orders.show', $sale)
            ->with('success', __('Sale :invoice has been cancelled.', ['invoice' => $sale->invoice_number]));
    }

    private function loadForReceipt(Sale $sale): Sale
    {
        return $sale->load(['items.modifiers', 'payments', 'customer', 'user', 'table', 'kitchenOrders.items']);
    }
}
