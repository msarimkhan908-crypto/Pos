<?php

namespace App\Http\Controllers;

use App\Enums\TableStatus;
use App\Http\Requests\RestaurantTableRequest;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Dining tables: a floor view with live status, plus create / edit / delete.
 */
class RestaurantTableController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:tables.view', only: ['index']),
            new Middleware('permission:tables.create', only: ['create', 'store']),
            new Middleware('permission:tables.edit', only: ['edit', 'update']),
            new Middleware('permission:tables.delete', only: ['destroy']),
            new Middleware('permission:tables.status', only: ['updateStatus']),
        ];
    }

    public function index(): View
    {
        $tables = RestaurantTable::query()->ordered()->withCount('openKitchenOrders')->get();

        $counts = collect(TableStatus::cases())
            ->mapWithKeys(fn (TableStatus $s) => [$s->value => $tables->where('is_active', true)->where('status', $s)->count()]);

        return view('restaurant.tables.index', compact('tables', 'counts'));
    }

    public function create(): View
    {
        return view('restaurant.tables.create', ['table' => new RestaurantTable]);
    }

    public function store(RestaurantTableRequest $request): RedirectResponse
    {
        $table = RestaurantTable::create($request->tableData());

        return redirect()->route('restaurant.tables.index')->with('success', __('Table ":name" created.', ['name' => $table->name]));
    }

    public function edit(RestaurantTable $table): View
    {
        return view('restaurant.tables.edit', compact('table'));
    }

    public function update(RestaurantTableRequest $request, RestaurantTable $table): RedirectResponse
    {
        $table->update($request->tableData());

        return redirect()->route('restaurant.tables.index')->with('success', __('Table ":name" updated.', ['name' => $table->name]));
    }

    /**
     * Set a table Available, Occupied or Reserved (Tables page or the POS table picker).
     */
    public function updateStatus(Request $request, RestaurantTable $table): RedirectResponse|JsonResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(TableStatus::class)]]);
        $table->update(['status' => $validated['status']]);

        $message = __(':name is now :status.', ['name' => $table->name, 'status' => $table->status->label()]);

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'status' => $table->status->value])
            : back()->with('success', $message);
    }

    /**
     * Tables with sales keep their history; deactivate them instead.
     */
    public function destroy(RestaurantTable $table): RedirectResponse
    {
        if ($table->sales()->exists()) {
            return back()->with('error', __('Table ":name" has orders and cannot be deleted. Deactivate it instead.', ['name' => $table->name]));
        }

        $table->delete();

        return redirect()->route('restaurant.tables.index')->with('success', __('Table ":name" deleted.', ['name' => $table->name]));
    }
}
