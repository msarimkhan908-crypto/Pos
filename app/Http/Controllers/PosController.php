<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartRequest;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Services\CartResolver;
use App\Services\SaleService;
use App\Support\BusinessDay;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * The cashier screen and its JSON endpoints: product search, barcode lookup and
 * server-side cart pricing. Checkout lives in SaleController, held sales in HeldSaleController.
 */
class PosController extends Controller implements HasMiddleware
{
    /** Product cards returned per search. */
    public const PAGE_SIZE = 60;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:sales.create', except: ['recentSales', 'products', 'lookup']),
            new Middleware('permission:sales.create|sales.view', only: ['recentSales']),
            new Middleware('permission:sales.create|sales.edit', only: ['products', 'lookup']),
        ];
    }

    public function index(SaleService $sales): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $query) => $query->active())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('pos.index', [
            'categories' => $categories,
            'customers' => $sales->customerOptions(),
        ]);
    }

    /**
     * Product cards, filtered by search (name, SKU, barcode) and category.
     */
    public function products(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->limit(100, '')->toString();
        $category = $request->integer('category') ?: null;

        $products = CartResolver::sellable()
            ->search($search ?: null)
            ->when($category, fn (Builder $query, int $id) => $query->where('products.category_id', $id))
            ->with(CartResolver::eagerLoads())
            ->withCount('variants')
            ->orderBy('products.name')
            ->limit(self::PAGE_SIZE + 1)
            ->get()
            // A variant product whose variants are all inactive has nothing to sell.
            ->reject(fn (Product $product) => $product->variants_count && $product->variants->isEmpty());

        return response()->json([
            'products' => $products->take(self::PAGE_SIZE)->map($this->present(...))->values(),
            'more' => $products->count() > self::PAGE_SIZE,
        ]);
    }

    /**
     * Exact barcode / SKU match for the scanner, on a product or one of its variants.
     */
    public function lookup(Request $request): JsonResponse
    {
        $code = $request->string('code')->trim()->limit(64, '')->toString();
        $notFound = response()->json(['message' => __('Product not found.')], 404);

        if ($code === '') {
            return $notFound;
        }

        $product = CartResolver::sellable()
            ->where(fn (Builder $query) => $query->where('products.barcode', $code)->orWhere('products.sku', $code))
            ->with(CartResolver::eagerLoads())
            ->first();

        $variantId = null;

        if (! $product) {
            $variant = ProductVariant::query()
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->where('barcode', $code)->orWhere('sku', $code))
                ->whereIn('product_id', CartResolver::sellable()->select('products.id'))
                ->first();

            $product = $variant ? CartResolver::sellable()->with(CartResolver::eagerLoads())->find($variant->product_id) : null;
            $variantId = $variant?->id;
        }

        if (! $product) {
            return $notFound;
        }

        return response()->json(['product' => $this->present($product), 'variant_id' => $variantId]);
    }

    /**
     * Active tables with their live status, for the Dine In table picker.
     */
    public function tables(): JsonResponse
    {
        $tables = RestaurantTable::query()->active()->ordered()->withCount('openKitchenOrders')->get();

        return response()->json([
            'tables' => $tables->map(fn (RestaurantTable $table) => [
                'id' => $table->id,
                'name' => $table->name,
                'section' => $table->section,
                'capacity' => $table->capacity,
                'status' => $table->status->value,
                'status_label' => $table->status->label(),
                'open_orders' => $table->open_kitchen_orders_count,
            ]),
            'can_change_status' => (bool) auth()->user()?->can('tables.status'),
        ]);
    }

    /**
     * Server-calculated lines and totals for the current cart. The screen shows these
     * totals; checkout recalculates them again.
     */
    public function quote(CartRequest $request, SaleService $sales): JsonResponse
    {
        $quote = $sales->quote($request->cart());

        return response()->json([
            'lines' => collect($quote['lines'])->map(fn (array $line) => [
                'unit_price' => $line['unit_price'],
                'gross' => $line['gross'],
                'discount' => $line['discount'],
                'total' => $line['total'],
                'tax' => $line['tax'],
            ]),
            ...collect($quote)->only(['subtotal', 'item_discount', 'order_discount', 'discount', 'tax', 'delivery_fee', 'total']),
        ]);
    }

    /**
     * Today's completed sales for the POS history panel.
     */
    public function recentSales(): JsonResponse
    {
        $date = BusinessDay::current(CarbonImmutable::now())->toDateString();

        $sales = Sale::query()
            ->where('status', 'completed')
            ->where('business_date', $date)
            ->withCount('items')
            ->orderByDesc('completed_at')
            ->limit(100)
            ->get();

        return response()->json([
            'sales' => $sales->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'order_no' => $sale->order_no,
                'invoice_number' => $sale->invoice_number,
                'customer_name' => $sale->customer_name,
                'total' => (string) $sale->total,
                'order_type' => $sale->order_type->label(),
                'item_count' => $sale->items_count,
                'time' => Format::time($sale->completed_at),
                'print_url' => route('sales.print', $sale, false),
                'edit_url' => route('sales.edit', $sale, false),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Product $product): array
    {
        $price = fn (?ProductVariant $variant = null) => number_format(
            $product->discountedPrice($variant ? (float) ($variant->selling_price ?? $product->selling_price) : null), 2, '.', ''
        );

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'image_url' => $product->image_url,
            'placeholder_color' => $product->placeholder_color,
            'placeholder_icon' => $product->placeholder_icon,
            'price' => $price(),
            'is_deal' => (bool) $product->is_deal,
            'deal_items' => $product->is_deal
                ? $product->dealItems->map(fn ($di) => [
                    'component_name' => $di->component?->name ?? '',
                    'variant_name' => $di->variant?->name,
                    'quantity' => $di->quantity,
                ])->values()
                : [],
            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'price' => $price($v),
            ])->values(),
            'modifier_groups' => $product->modifierGroups->map(fn (ModifierGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'min' => $g->min_select,
                'max' => $g->max_select,
                'rule' => $g->selectionRule(),
                'modifiers' => $g->modifiers->map(fn (Modifier $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'price' => (string) $m->price,
                ])->values(),
            ])->values(),
        ];
    }
}
