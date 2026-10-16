<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
use App\Enums\OrderType;
use App\Enums\RecordStatus;
use App\Enums\TableStatus;
use App\Models\RestaurantTable;
use App\Services\SaleService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shape of a POS cart. Only ids, quantities and discounts are accepted; prices and
 * totals are looked up and calculated by App\Services\SaleService. Product, variant,
 * modifier and discount-limit rules are checked there against the database.
 */
class CartRequest extends FormRequest
{
    public const MAX_LINES = 200;

    public const MAX_QUANTITY = 9999;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('sales.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'items.*' => ['array'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
            'items.*.modifier_ids' => ['nullable', 'array', 'max:50'],
            'items.*.modifier_ids.*' => ['integer', 'distinct'],
            'items.*.discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:'.SaleService::MAX_AMOUNT],
            'items.*.note' => ['nullable', 'string', 'max:200'],
            'order_type' => ['nullable', Rule::enum(OrderType::class)],
            // Only Dine In orders keep a table; it is required for them.
            'table_id' => ['exclude_unless:order_type,dine_in', 'required', 'integer', Rule::exists('restaurant_tables', 'id')->where('is_active', true)],
            'order_note' => ['nullable', 'string', 'max:500'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('status', RecordStatus::Active->value)],
            'customer_name' => ['nullable', 'string', 'max:150', 'required_if_accepted:save_customer'],
            'customer_phone' => ['nullable', 'string', 'regex:'.SupplierRequest::PHONE_PATTERN],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'save_customer' => ['nullable', 'boolean'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:'.SaleService::MAX_AMOUNT],
            'order_discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'order_discount_value' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:'.SaleService::MAX_AMOUNT],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => __('The cart is empty.'),
            'items.min' => __('The cart is empty.'),
            'items.*.quantity.min' => __('Quantity must be at least 1.'),
            'items.*.quantity.max' => __('Quantity cannot be more than :max.'),
            'customer_id.exists' => __('Choose an active customer.'),
            'customer_name.required_if_accepted' => __('Enter a name to save this customer.'),
            'customer_phone.regex' => __('Enter a valid phone number (digits, spaces, + - ( ) only).'),
            'table_id.required' => __('Select a table for this Dine In order.'),
            'table_id.exists' => __('Select an active table.'),
        ];
    }

    /**
     * Saving a new customer needs customers.create; a delivery fee needs a Delivery order.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->boolean('save_customer') && blank($this->input('customer_id')) && ! $this->user()->can('customers.create')) {
                    $validator->errors()->add('save_customer', __('You do not have permission to add customers.'));
                }

                if ($this->input('order_type') === OrderType::DineIn->value && filled($this->input('table_id'))) {
                    $table = RestaurantTable::find($this->input('table_id'));

                    if ($table?->status === TableStatus::Reserved) {
                        $validator->errors()->add('table_id', __(':table is reserved. Mark it Available or Occupied first to seat guests.', ['table' => $table->name]));
                    }
                }

                if ((float) $this->input('delivery_fee') > 0 && $this->input('order_type') !== OrderType::Delivery->value) {
                    $validator->errors()->add('delivery_fee', __('A delivery fee can only be charged on Delivery orders.'));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cart(): array
    {
        return [
            ...$this->safe()->only([
                'items', 'order_type', 'table_id', 'order_note', 'customer_id', 'customer_name', 'customer_phone', 'customer_address',
                'delivery_fee', 'order_discount_type', 'order_discount_value',
            ]),
            'save_customer' => $this->boolean('save_customer'),
        ];
    }
}
