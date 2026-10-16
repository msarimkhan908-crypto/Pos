<?php

namespace App\Http\Requests;

use App\Enums\MoneyMethod;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A return: which lines of the sale and how many units. Refund amounts are never accepted
 * from the browser; App\Services\ReturnService calculates them and checks quantities.
 */
class ReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('returns.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sale_id' => ['required', 'integer', Rule::exists('sales', 'id')],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'refund_method' => ['required', Rule::enum(MoneyMethod::class)],
            'status' => ['required', Rule::in([ReturnStatus::Pending->value, ReturnStatus::Completed->value])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['array'],
            'items.*.sale_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:9999'],
            'items.*.reason' => ['nullable', Rule::enum(ReturnReason::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.quantity.min' => __('Quantities cannot be negative.'),
            'items.*.quantity.integer' => __('Enter whole units.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function returnData(): array
    {
        return [
            ...$this->safe()->only(['reason', 'refund_method', 'status', 'notes']),
            'items' => array_values($this->validated('items')),
        ];
    }
}
