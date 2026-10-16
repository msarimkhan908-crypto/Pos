<?php

namespace App\Http\Requests;

use App\Enums\MoneyMethod;
use App\Enums\RecordStatus;
use App\Models\Expense;
use App\Services\SaleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->expense() ? 'expenses.edit' : 'expenses.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // An inactive category can't be picked for a new expense; an existing one keeps its own.
            'category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where(fn ($q) => $q
                ->where('status', RecordStatus::Active->value)
                ->when($this->expense(), fn ($q, Expense $e) => $q->orWhere('id', $e->category_id)))],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:'.SaleService::MAX_AMOUNT],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::enum(MoneyMethod::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => __('Choose an active category.'),
            'amount.gt' => __('The amount must be more than zero.'),
            'expense_date.before_or_equal' => __('The expense date cannot be in the future.'),
        ];
    }

    private function expense(): ?Expense
    {
        return $this->route('expense');
    }
}
