<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SupplierController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:suppliers.view', only: ['index', 'show']),
            new Middleware('permission:suppliers.create', only: ['create', 'store']),
            new Middleware('permission:suppliers.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:suppliers.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->limit(100, '')->toString();
        $status = $request->enum('status', RecordStatus::class);

        $suppliers = Supplier::query()
            ->search($search ?: null)
            ->when($status, fn ($query, RecordStatus $status) => $query->where('status', $status))
            ->withCount('purchases')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers', 'search', 'status'));
    }

    public function create(): View
    {
        return view('suppliers.create', ['supplier' => new Supplier]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', __('Supplier ":name" created.', ['name' => $supplier->name]));
    }

    public function show(Supplier $supplier): View
    {
        $supplier->loadCount('purchases');

        $purchases = $supplier->purchases()
            ->with('creator:id,name')
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $totalPurchased = $supplier->purchases()->where('status', '!=', 'cancelled')->sum('total');

        return view('suppliers.show', compact('supplier', 'purchases', 'totalPurchased'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', __('Supplier ":name" updated.', ['name' => $supplier->name]));
    }

    public function toggle(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['status' => $supplier->isActive() ? RecordStatus::Inactive : RecordStatus::Active]);

        return back()->with('success', $supplier->isActive()
            ? __('Supplier ":name" activated.', ['name' => $supplier->name])
            : __('Supplier ":name" deactivated.', ['name' => $supplier->name]));
    }

    /**
     * Suppliers with purchase history cannot be deleted; deactivate them instead.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $count = $supplier->purchases()->count();

        if ($count > 0) {
            return back()->with('error', trans_choice(
                'Supplier ":name" has :count purchase and cannot be deleted. Deactivate the supplier instead.|Supplier ":name" has :count purchases and cannot be deleted. Deactivate the supplier instead.',
                $count,
                ['name' => $supplier->name],
            ));
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', __('Supplier ":name" deleted.', ['name' => $supplier->name]));
    }
}
