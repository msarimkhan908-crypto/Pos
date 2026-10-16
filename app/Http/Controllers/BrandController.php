<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrandRequest;
use App\Models\Brand;
use App\Support\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class BrandController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:brands.view', only: ['index', 'show']),
            new Middleware('permission:brands.create', only: ['create', 'store']),
            new Middleware('permission:brands.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:brands.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->input('status');

        $brands = Brand::query()
            ->search($search ?: null)
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->withCount('products')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('brands.index', compact('brands', 'search', 'status'));
    }

    public function create(): View
    {
        return view('brands.create', ['brand' => new Brand(['is_active' => true])]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $brand = Brand::create([
            ...$request->brandData(),
            'logo_path' => ImageStorage::replace($request->file('logo'), null, 'brands'),
        ]);

        return redirect()->route('brands.show', $brand)
            ->with('success', __('Brand ":name" created.', ['name' => $brand->name]));
    }

    public function show(Brand $brand): View
    {
        $brand->loadCount('products');

        $products = $brand->products()
            ->with('category:id,name')
            ->orderBy('name')
            ->limit(10)
            ->get();

        return view('brands.show', compact('brand', 'products'));
    }

    public function edit(Brand $brand): View
    {
        return view('brands.edit', compact('brand'));
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update([
            ...$request->brandData(),
            'logo_path' => ImageStorage::replace($request->file('logo'), $brand->logo_path, 'brands', $request->boolean('remove_logo')),
        ]);

        return redirect()->route('brands.show', $brand)
            ->with('success', __('Brand ":name" updated.', ['name' => $brand->name]));
    }

    public function toggle(Brand $brand): RedirectResponse
    {
        $brand->update(['is_active' => ! $brand->is_active]);

        return back()->with('success', $brand->is_active
            ? __('Brand ":name" activated.', ['name' => $brand->name])
            : __('Brand ":name" deactivated.', ['name' => $brand->name]));
    }

    /**
     * Brands in use cannot be deleted; deactivate them instead.
     */
    public function destroy(Brand $brand): RedirectResponse
    {
        $count = $brand->products()->count();

        if ($count > 0) {
            return back()->with('error', trans_choice(
                'Brand ":name" is used by :count product. Change its brand or deactivate the brand instead.|Brand ":name" is used by :count products. Change their brand or deactivate the brand instead.',
                $count,
                ['name' => $brand->name],
            ));
        }

        ImageStorage::delete($brand->logo_path);
        $brand->delete();

        return redirect()->route('brands.index')
            ->with('success', __('Brand ":name" deleted.', ['name' => $brand->name]));
    }
}
