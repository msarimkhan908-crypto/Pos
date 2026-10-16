<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DealItem;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Support\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:products.view', only: ['index', 'show']),
            new Middleware('permission:products.create', only: ['create', 'store']),
            new Middleware('permission:products.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:products.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->string('search')->trim()->limit(100, '')->toString(),
            'category' => $request->integer('category') ?: null,
            'brand' => $request->integer('brand') ?: null,
            'status' => in_array($request->input('status'), ['active', 'inactive'], true) ? $request->input('status') : null,
        ];

        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->withCount('variants')
            ->search($filters['search'] ?: null)
            ->when($filters['category'], fn ($query, $id) => $query->where('products.category_id', $id))
            ->when($filters['brand'], fn ($query, $id) => $query->where('products.brand_id', $id))
            ->when($filters['status'], fn ($query, $status) => $query->where('products.is_active', $status === 'active'))
            ->orderBy('products.name')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::query()->ordered()->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'product' => new Product(['is_active' => true, 'tax_rate' => settings('tax.default_rate')]),
            ...$this->formOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $imagePath = ImageStorage::replace($request->file('image'), null, 'products');

        try {
            $product = DB::transaction(function () use ($request, $imagePath) {
                $product = Product::create([...$request->productData(), 'image_path' => $imagePath]);
                $product->modifierGroups()->sync($request->modifierGroupSync());
                $this->syncVariants($product, $request->variantRows());
                $this->syncDealItems($product, $request->dealItemRows());

                return $product;
            });
        } catch (Throwable $e) {
            ImageStorage::delete($imagePath);

            throw $e;
        }

        return redirect()->route('products.show', $product)
            ->with('success', __('Product ":name" created.', ['name' => $product->name]));
    }

    public function show(Product $product): View
    {
        $product->load([
            'category:id,name',
            'brand:id,name',
            'variants',
            'modifierGroups.modifiers',
        ]);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $product->load(['modifierGroups:id', 'variants', 'dealItems.component:id,name', 'dealItems.variant:id,name']);

        return view('products.edit', ['product' => $product, ...$this->formOptions()]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            $product->update([
                ...$request->productData(),
                'image_path' => ImageStorage::replace($request->file('image'), $product->image_path, 'products', $request->boolean('remove_image')),
            ]);
            $product->modifierGroups()->sync($request->modifierGroupSync());
            $this->syncVariants($product, $request->variantRows());
            $this->syncDealItems($product, $request->dealItemRows());
        });

        return redirect()->route('products.show', $product)
            ->with('success', __('Product ":name" updated.', ['name' => $product->name]));
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', $product->is_active
            ? __('Product ":name" activated.', ['name' => $product->name])
            : __('Product ":name" deactivated.', ['name' => $product->name]));
    }

    /**
     * Soft delete, so future sales can keep referencing the product.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', __('Product ":name" deleted.', ['name' => $product->name]));
    }

    /**
     * Make the product's variants match the submitted rows: update existing ones, create new
     * ones and soft-delete the rest. An empty list makes it a simple product.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncVariants(Product $product, array $rows): void
    {
        $keepIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        $product->variants()->whereNotIn('id', $keepIds)->get()->each->delete();

        $existing = $product->variants()->whereIn('id', $keepIds)->get()->keyBy('id');

        // Park codes being changed so two variants can swap SKUs or barcodes in one save
        // without tripping the unique indexes midway.
        foreach ($rows as $row) {
            $variant = filled($row['id'] ?? null) ? $existing->get((int) $row['id']) : null;

            if (! $variant) {
                continue;
            }

            $parked = [];

            if (filled($row['sku']) && $row['sku'] !== $variant->sku) {
                $parked['sku'] = "__renaming_{$variant->id}";
            }

            if (($row['barcode'] ?? null) !== $variant->barcode) {
                $parked['barcode'] = null;
            }

            if ($parked !== []) {
                $variant->forceFill($parked)->save();
            }
        }

        foreach (array_values($rows) as $position => $row) {
            $attributes = [
                'name' => $row['name'],
                'barcode' => $row['barcode'] ?? null,
                'selling_price' => $row['selling_price'] ?? null,
                'purchase_price' => $row['purchase_price'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? false),
                'sort_order' => $position,
            ];

            if (filled($row['sku'] ?? null)) {
                $attributes['sku'] = $row['sku'];
            }

            if (filled($row['id'] ?? null)) {
                $existing->get((int) $row['id'])->update($attributes);
            } else {
                $product->variants()->create($attributes);
            }
        }
    }

    /**
     * Replace all deal component rows for the product.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncDealItems(Product $product, array $rows): void
    {
        $product->dealItems()->delete();

        foreach (array_values($rows) as $position => $row) {
            DealItem::create([
                'product_id' => $product->id,
                'component_id' => (int) $row['component_id'],
                'variant_id' => filled($row['variant_id'] ?? null) ? (int) $row['variant_id'] : null,
                'quantity' => (int) ($row['quantity'] ?? 1),
                'sort_order' => $position,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->ordered()->get(['id', 'name', 'is_active']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'modifierGroups' => ModifierGroup::query()->ordered()->withCount('modifiers')->get(),
            'dealableProducts' => Product::query()->active()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
