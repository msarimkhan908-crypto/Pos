<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Support\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:categories.view', only: ['index', 'show']),
            new Middleware('permission:categories.create', only: ['create', 'store']),
            new Middleware('permission:categories.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:categories.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->input('status');

        $categories = Category::query()
            ->search($search ?: null)
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->withCount('products')
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('categories.index', compact('categories', 'search', 'status'));
    }

    public function create(): View
    {
        return view('categories.create', ['category' => new Category(['is_active' => true])]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create([
            ...$request->categoryData(),
            'image_path' => ImageStorage::replace($request->file('image'), null, 'categories'),
        ]);

        return redirect()->route('categories.show', $category)
            ->with('success', __('Category ":name" created.', ['name' => $category->name]));
    }

    public function show(Category $category): View
    {
        $category->loadCount('products');

        $products = $category->products()
            ->orderBy('name')
            ->limit(10)
            ->get();

        return view('categories.show', compact('category', 'products'));
    }

    public function edit(Category $category): View
    {
        return view('categories.edit', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update([
            ...$request->categoryData(),
            'image_path' => ImageStorage::replace($request->file('image'), $category->image_path, 'categories', $request->boolean('remove_image')),
        ]);

        return redirect()->route('categories.show', $category)
            ->with('success', __('Category ":name" updated.', ['name' => $category->name]));
    }

    public function toggle(Category $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', $category->is_active
            ? __('Category ":name" activated.', ['name' => $category->name])
            : __('Category ":name" deactivated.', ['name' => $category->name]));
    }

    /**
     * Categories in use cannot be deleted; deactivate them instead.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $count = $category->products()->count();

        if ($count > 0) {
            return back()->with('error', trans_choice(
                'Category ":name" is used by :count product. Move it to another category or deactivate the category instead.|Category ":name" is used by :count products. Move them to another category or deactivate the category instead.',
                $count,
                ['name' => $category->name],
            ));
        }

        ImageStorage::delete($category->image_path);
        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', __('Category ":name" deleted.', ['name' => $category->name]));
    }
}
