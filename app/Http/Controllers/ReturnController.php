<?php

namespace App\Http\Controllers;

use App\Enums\MoneyMethod;
use App\Enums\ReturnStatus;
use App\Http\Requests\ReturnRequest;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Return history, processing returns against completed sales, and completing / cancelling
 * pending returns. All amounts come from App\Services\ReturnService.
 */
class ReturnController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:returns.view', only: ['index', 'show']),
            new Middleware('permission:returns.create', only: ['create', 'store']),
            new Middleware('permission:returns.edit', only: ['updateStatus']),
            new Middleware('permission:returns.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ReturnStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $returns = SaleReturn::query()
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('return_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('return_date', '<=', $to))
            ->with(['sale:id,invoice_number,customer_name,customer_id', 'sale.customer:id,name', 'customer:id,name', 'processor:id,name'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('returns.index', compact('returns', 'filters'));
    }

    /**
     * Step 1: find the sale by invoice number (or ?sale=id). Step 2: choose items.
     */
    public function create(Request $request): View
    {
        $invoice = $request->string('invoice')->trim()->limit(30, '')->toString();
        $sale = null;
        $error = null;

        if ($request->filled('sale') || $invoice !== '') {
            $sale = Sale::query()
                ->when($request->integer('sale'), fn (Builder $q, int $id) => $q->whereKey($id), fn (Builder $q) => $q->where('invoice_number', $invoice))
                ->first();

            $error = match (true) {
                ! $sale => __('No sale found with invoice number ":invoice".', ['invoice' => $invoice ?: $request->input('sale')]),
                ! $sale->isCompleted() => __('Only completed sales can be returned.'),
                default => null,
            };

            if ($error) {
                $sale = null;
            } else {
                $sale->load(['items.modifiers', 'customer', 'user', 'returns' => fn ($q) => $q->latest('id')]);
            }
        }

        return view('returns.create', compact('sale', 'invoice', 'error'));
    }

    public function store(ReturnRequest $request, ReturnService $returns): RedirectResponse
    {
        $sale = Sale::findOrFail($request->validated('sale_id'));
        $return = $returns->create($sale, $request->returnData(), $request->user());

        return redirect()->route('returns.show', $return)
            ->with('success', $return->isPending()
                ? __('Return :number saved as pending.', ['number' => $return->return_number])
                : __('Return :number completed. Refund :amount.', ['number' => $return->return_number, 'amount' => Money::format($return->refund_amount)]));
    }

    public function show(SaleReturn $return): View
    {
        $return->load(['items.saleItem.modifiers', 'sale.customer', 'sale.user', 'processor']);

        return view('returns.show', compact('return'));
    }

    /**
     * Complete (refund paid) or cancel a pending return.
     */
    public function updateStatus(Request $request, SaleReturn $return, ReturnService $returns): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([ReturnStatus::Completed->value, ReturnStatus::Cancelled->value])],
            'refund_method' => ['required_if:status,completed', 'nullable', Rule::enum(MoneyMethod::class)],
        ]);

        try {
            $data['status'] === ReturnStatus::Completed->value
                ? $returns->complete($return, MoneyMethod::from($data['refund_method']))
                : $returns->cancel($return);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('Return :number is now :status.', [
            'number' => $return->return_number,
            'status' => ReturnStatus::from($data['status'])->label(),
        ]));
    }

    public function destroy(SaleReturn $return, ReturnService $returns): RedirectResponse
    {
        try {
            $returns->delete($return);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('returns.index')->with('success', __('Return :number deleted.', ['number' => $return->return_number]));
    }

    /**
     * Rows for the return form: what was sold, what is left, and what was paid (for the estimate).
     *
     * @return list<array<string, mixed>>
     */
    public static function formRows(Sale $sale): array
    {
        return $sale->items->map(fn (SaleItem $item) => [
            'id' => $item->id,
            'name' => $item->displayName(),
            'modifiers' => $item->modifiers->pluck('name')->all(),
            'sold' => $item->quantity,
            'returned' => $item->returned_quantity,
            'returnable' => $item->returnableQuantity(),
            'unit_price' => (string) $item->unit_price,
            'paid' => $item->paidAmount(),
        ])->values()->all();
    }
}
