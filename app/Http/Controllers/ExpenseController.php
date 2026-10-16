<?php

namespace App\Http\Controllers;

use App\Enums\MoneyMethod;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Money;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:expenses.view', only: ['index', 'show']),
            new Middleware('permission:expenses.create', only: ['create', 'store']),
            new Middleware('permission:expenses.edit', only: ['edit', 'update']),
            new Middleware('permission:expenses.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::enum(MoneyMethod::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Expense::query()
            ->search($filters['search'] ?? null)
            ->when($filters['category'] ?? null, fn (Builder $q, int $id) => $q->where('category_id', $id))
            ->when($filters['method'] ?? null, fn (Builder $q, string $m) => $q->where('payment_method', $m))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('expense_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('expense_date', '<=', $to));

        $total = (clone $query)->sum('amount');
        $expenses = $query->with(['category:id,name', 'creator:id,name'])
            ->latest('expense_date')->latest('id')
            ->paginate(15)
            ->withQueryString();

        $categories = ExpenseCategory::query()->orderBy('name')->pluck('name', 'id');

        return view('expenses.index', compact('expenses', 'filters', 'categories', 'total'));
    }

    public function create(): View
    {
        return view('expenses.create', ['expense' => new Expense(['expense_date' => today(), 'payment_method' => MoneyMethod::Cash]), ...$this->formOptions()]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $expense = DB::transaction(function () use ($request) {
            $expense = new Expense($request->validated());
            $expense->forceFill([
                'expense_number' => Expense::numberFor(NumberSequence::next(Expense::NUMBER_SEQUENCE)),
                'created_by' => $request->user()->getKey(),
            ])->save();

            return $expense;
        });

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('Expense :number recorded (:amount).', ['number' => $expense->expense_number, 'amount' => Money::format($expense->amount)]));
    }

    public function show(Expense $expense): View
    {
        $expense->load(['category', 'creator']);

        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense): View
    {
        return view('expenses.edit', ['expense' => $expense, ...$this->formOptions($expense)]);
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->update($request->validated());

        return redirect()->route('expenses.show', $expense)->with('success', __('Expense :number updated.', ['number' => $expense->expense_number]));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', __('Expense :number deleted.', ['number' => $expense->expense_number]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?Expense $expense = null): array
    {
        return [
            'categories' => ExpenseCategory::query()
                ->where(fn (Builder $q) => $q->active()->when($expense, fn ($q) => $q->orWhere('id', $expense->category_id)))
                ->orderBy('name')
                ->pluck('name', 'id'),
        ];
    }
}
