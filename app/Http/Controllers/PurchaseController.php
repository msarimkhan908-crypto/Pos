<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseException;
use App\Http\Requests\PurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseController extends Controller implements HasMiddleware
{
    public function __construct(private PurchaseService $purchases) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:purchases.view', only: ['index', 'show']),
            new Middleware('permission:purchases.create', only: ['create', 'store']),
            new Middleware('permission:purchases.edit', only: ['edit', 'update', 'updateStatus', 'updatePaymentStatus']),
            new Middleware('permission:purchases.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $filters = [
            'search' => $request->string('search')->trim()->limit(100, '')->toString(),
            'supplier' => $request->integer('supplier') ?: null,
            'status' => $request->enum('status', PurchaseStatus::class),
            'payment_status' => $request->enum('payment_status', PaymentStatus::class),
            'from' => $request->filled('from') ? Carbon::parse($request->input('from'))->toDateString() : null,
            'to' => $request->filled('to') ? Carbon::parse($request->input('to'))->toDateString() : null,
        ];

        $purchases = Purchase::query()
            ->with(['supplier:id,name,company_name', 'creator:id,name'])
            ->search($filters['search'] ?: null)
            ->when($filters['supplier'], fn ($query, int $id) => $query->where('supplier_id', $id))
            ->when($filters['status'], fn ($query, PurchaseStatus $status) => $query->where('status', $status))
            ->when($filters['payment_status'], fn ($query, PaymentStatus $status) => $query->where('payment_status', $status))
            ->when($filters['from'], fn ($query, string $from) => $query->whereDate('purchase_date', '>=', $from))
            ->when($filters['to'], fn ($query, string $to) => $query->whereDate('purchase_date', '<=', $to))
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('purchases.index', [
            'purchases' => $purchases,
            'filters' => $filters,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $purchase = new Purchase([
            'supplier_id' => $request->integer('supplier') ?: null,
            'purchase_date' => now()->toDateString(),
        ]);

        return view('purchases.create', ['purchase' => $purchase, ...$this->formOptions()]);
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->purchases->create($request->purchaseData(), $request->items(), $request->user());

        return redirect()->route('purchases.show', $purchase)
            ->with('success', __('Purchase :number saved.', ['number' => $purchase->purchase_number]));
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['supplier', 'creator:id,name', 'items.product:id,name,sku,deleted_at', 'items.variant:id,name,sku,deleted_at']);

        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase): View|RedirectResponse
    {
        if (! $purchase->isDraft()) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', PurchaseException::notEditable($purchase->status)->getMessage());
        }

        $purchase->load('items');

        return view('purchases.edit', ['purchase' => $purchase, ...$this->formOptions($purchase)]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchases->update($purchase, $request->purchaseData(), $request->items());
        } catch (PurchaseException $e) {
            return redirect()->route('purchases.show', $purchase)->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)
            ->with('success', __('Purchase :number updated.', ['number' => $purchase->purchase_number]));
    }

    public function updateStatus(Request $request, Purchase $purchase): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(PurchaseStatus::class)]]);

        try {
            $this->purchases->changeStatus($purchase, PurchaseStatus::from($validated['status']));
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Purchase :number marked as :status.', [
            'number' => $purchase->purchase_number,
            'status' => strtolower($purchase->status->label()),
        ]));
    }

    public function updatePaymentStatus(Request $request, Purchase $purchase): RedirectResponse
    {
        $validated = $request->validate(['payment_status' => ['required', Rule::enum(PaymentStatus::class)]]);

        try {
            $this->purchases->changePaymentStatus($purchase, PaymentStatus::from($validated['payment_status']));
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Payment status updated to :status.', ['status' => strtolower($purchase->payment_status->label())]));
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchases->delete($purchase);
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.index')
            ->with('success', __('Draft purchase :number deleted.', ['number' => $purchase->purchase_number]));
    }

    /**
     * Suppliers and products for the purchase form. Products are sent to the browser as a
     * compact list (id, name, sku, cost, variants) for the item picker.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?Purchase $purchase = null): array
    {
        $suppliers = Supplier::query()
            ->where(fn ($query) => $query->active()->when($purchase, fn ($query) => $query->orWhere('id', $purchase->supplier_id)))
            ->orderBy('name')
            ->get(['id', 'name', 'company_name']);

        $usedProductIds = $purchase?->items->pluck('product_id')->all() ?? [];

        $products = Product::query()
            ->where(fn ($query) => $query->active()->orWhereIn('id', $usedProductIds))
            ->with(['variants' => fn ($query) => $query->select(['id', 'product_id', 'name', 'sku', 'purchase_price', 'is_active'])])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'barcode', 'purchase_price'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'cost' => (string) $product->purchase_price,
                'variants' => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'sku' => $variant->sku,
                    'cost' => (string) ($variant->purchase_price ?? $product->purchase_price),
                    'active' => $variant->is_active,
                ])->values(),
            ])
            ->values();

        return compact('suppliers', 'products');
    }
}
