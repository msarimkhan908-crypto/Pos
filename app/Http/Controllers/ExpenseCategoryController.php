<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Expense categories (Rent, Electricity, Internet…). Listed with expenses.view; created and
 * changed with expenses.edit; deleted with expenses.delete (only when unused).
 */
class ExpenseCategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:expenses.view', only: ['index']),
            new Middleware('permission:expenses.edit', only: ['create', 'store', 'edit', 'update']),
            new Middleware('permission:expenses.delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $categories = ExpenseCategory::query()->withCount('expenses')->withSum('expenses', 'amount')->orderBy('name')->get();

        return view('expense-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('expense-categories.form', ['category' => new ExpenseCategory]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = ExpenseCategory::create($this->validated($request));

        return redirect()->route('expense-categories.index')->with('success', __('Category ":name" created.', ['name' => $category->name]));
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        return view('expense-categories.form', ['category' => $expenseCategory]);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update($this->validated($request, $expenseCategory));

        return redirect()->route('expense-categories.index')->with('success', __('Category ":name" updated.', ['name' => $expenseCategory->name]));
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        if ($expenseCategory->expenses()->exists()) {
            return back()->with('error', __('Category ":name" has expenses and cannot be deleted. Deactivate it instead.', ['name' => $expenseCategory->name]));
        }

        $expenseCategory->delete();

        return redirect()->route('expense-categories.index')->with('success', __('Category ":name" deleted.', ['name' => $expenseCategory->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ExpenseCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories')->ignore($category)],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ]);
    }
}
