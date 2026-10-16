<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class CustomerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:customers.view', only: ['index', 'show']),
            new Middleware('permission:customers.create', only: ['create', 'store']),
            new Middleware('permission:customers.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:customers.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->limit(100, '')->toString();
        $status = $request->enum('status', RecordStatus::class);

        $customers = Customer::query()
            ->search($search ?: null)
            ->when($status, fn ($query, RecordStatus $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers', 'search', 'status'));
    }

    public function create(): View
    {
        return view('customers.create', ['customer' => new Customer]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Customer ":name" created.', ['name' => $customer->name]));
    }

    public function show(Customer $customer): View
    {
        $sales = $customer->sales()->completed()->latest('completed_at')->latest('id')->paginate(10);
        $totalSpent = $customer->sales()->completed()->sum('total');

        return view('customers.show', compact('customer', 'sales', 'totalSpent'));
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Customer ":name" updated.', ['name' => $customer->name]));
    }

    public function toggle(Customer $customer): RedirectResponse
    {
        $customer->update(['status' => $customer->isActive() ? RecordStatus::Inactive : RecordStatus::Active]);

        return back()->with('success', $customer->isActive()
            ? __('Customer ":name" activated.', ['name' => $customer->name])
            : __('Customer ":name" deactivated.', ['name' => $customer->name]));
    }

    /**
     * No sales exist yet, so customers can be deleted. When the Sales module lands,
     * block deletion for customers with sales (deactivate them instead).
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        // Completed and held sales both reference the customer.
        if ($customer->sales()->exists()) {
            return back()->with('error', __('Customer ":name" has sales and cannot be deleted. Deactivate the customer instead.', ['name' => $customer->name]));
        }

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', __('Customer ":name" deleted.', ['name' => $customer->name]));
    }
}
