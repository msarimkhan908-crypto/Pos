<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Services\SaleService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A cart plus how it is paid. Whether the amounts cover the server-calculated total
 * is checked by App\Services\SaleService.
 */
class CheckoutRequest extends CartRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $money = ['numeric', 'min:0', 'decimal:0,2', 'max:'.SaleService::MAX_AMOUNT];

        return [
            ...parent::rules(),
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'cash_received' => ['nullable', 'required_if:payment_method,cash', ...$money],
            'cash_amount' => ['nullable', 'required_if:payment_method,mixed', ...$money],
            'card_amount' => ['nullable', 'required_if:payment_method,card,mixed', ...$money],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'cash_received.required_if' => __('Enter the cash received.'),
            'cash_amount.required_if' => __('Enter the cash amount.'),
            'card_amount.required_if' => __('Enter the card amount.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payment(): array
    {
        return $this->safe()->only(['payment_method', 'cash_received', 'cash_amount', 'card_amount']);
    }
}
