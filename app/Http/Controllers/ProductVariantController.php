<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class ProductVariantController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:products.edit'),
        ];
    }

    public function create(Product $product): View
    {
        return view('products.variants.create', [
            'product' => $product,
            'variant' => new ProductVariant(['is_active' => true]),
        ]);
    }

    public function store(ProductVariantRequest $request, Product $product): RedirectResponse
    {
        $variant = $product->variants()->create($request->variantData());

        return redirect()->route('products.show', $product)
            ->with('success', __('Variant ":name" added.', ['name' => $variant->name]));
    }

    public function edit(Product $product, ProductVariant $variant): View
    {
        return view('products.variants.edit', compact('product', 'variant'));
    }

    public function update(ProductVariantRequest $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $variant->update($request->variantData());

        return redirect()->route('products.show', $product)
            ->with('success', __('Variant ":name" updated.', ['name' => $variant->name]));
    }

    /**
     * Soft delete, so future sales can keep referencing the variant.
     */
    public function destroy(Product $product, ProductVariant $variant): RedirectResponse
    {
        $variant->delete();

        return redirect()->route('products.show', $product)
            ->with('success', __('Variant ":name" deleted.', ['name' => $variant->name]));
    }
}
