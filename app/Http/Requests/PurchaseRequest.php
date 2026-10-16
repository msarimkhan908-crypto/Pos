<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Services\PurchaseCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or update a (draft) purchase. Totals are never read from the request:
 * PurchaseService recalculates them from the validated items.
 */
class PurchaseRequest extends FormRequest
{
    public const MAX_ITEMS = 200;

    public const MAX_AMOUNT = 9999999999.99;

    public const MAX_QUANTITY = 1000000;

    /** Largest value the decimal(14,2) money columns can hold. */
    public const MAX_TOTAL = '999999999999.99';

    public function authorize(): bool
    {
        return $this->user()->can($this->purchase() ? 'purchases.edit' : 'purchases.create');
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                'product_id' => $row['product_id'] ?? null,
                'variant_id' => filled($row['variant_id'] ?? null) ? $row['variant_id'] : null,
                'quantity' => $row['quantity'] ?? null,
                'purchase_price' => $row['purchase_price'] ?? null,
                'discount' => filled($row['discount'] ?? null) ? $row['discount'] : '0',
                'tax' => filled($row['tax'] ?? null) ? $row['tax'] : '0',
            ])
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            // New purchases need an active supplier; a draft may keep its current (now inactive) one.
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where(function ($query) {
                $query->where('status', RecordStatus::Active->value)
                    ->when($this->purchase(), fn ($query, Purchase $purchase) => $query->orWhere('id', $purchase->supplier_id));
            })],
            'purchase_date' => ['required', 'date', 'after_or_equal:2000-01-01'],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            // Save as draft or complete it; cancelling has its own action.
            'status' => ['required', Rule::enum(PurchaseStatus::class)->only([PurchaseStatus::Draft, PurchaseStatus::Completed])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:'.self::MAX_QUANTITY],
            'items.*.purchase_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_AMOUNT],
            'items.*.discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_AMOUNT],
            'items.*.tax' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_AMOUNT],
        ];

        // A variant must belong to the product chosen on the same row.
        foreach ($this->input('items', []) as $index => $row) {
            $rules["items.{$index}.variant_id"] = [
                'nullable', 'integer',
                Rule::exists('product_variants', 'id')
                    ->where('product_id', (int) ($row['product_id'] ?? 0))
                    ->whereNull('deleted_at'),
            ];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateVariantsChosen($validator),
            fn (Validator $validator) => $this->validateDiscounts($validator),
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'items' => 'items',
            'items.*.product_id' => 'product',
            'items.*.variant_id' => 'variant',
            'items.*.quantity' => 'quantity',
            'items.*.purchase_price' => 'purchase price',
            'items.*.discount' => 'discount',
            'items.*.tax' => 'tax',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => __('Add at least one item.'),
            'items.min' => __('Add at least one item.'),
            'items.*.quantity.gt' => __('Quantity must be greater than zero.'),
            'items.*.variant_id.exists' => __('Choose a variant of the selected product.'),
            'supplier_id.exists' => __('Choose an active supplier.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function purchaseData(): array
    {
        return $this->safe()->only(['supplier_id', 'purchase_date', 'payment_status', 'status', 'notes']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return array_values($this->validated('items'));
    }

    /**
     * Products sold as variants must be bought per variant.
     */
    private function validateVariantsChosen(Validator $validator): void
    {
        $rows = collect($this->input('items', []));
        $productIds = $rows->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique();

        $withVariants = Product::query()
            ->whereIn('id', $productIds)
            ->whereHas('variants')
            ->pluck('id')
            ->all();

        foreach ($rows as $index => $row) {
            if (in_array((int) ($row['product_id'] ?? 0), $withVariants, true) && blank($row['variant_id'] ?? null)) {
                $validator->errors()->add("items.{$index}.variant_id", __('Choose a variant for this product.'));
            }
        }
    }

    /**
     * Discounts may not exceed the line amount, and amounts must fit the money columns.
     */
    private function validateDiscounts(Validator $validator): void
    {
        $calculator = app(PurchaseCalculator::class);
        $total = '0';

        foreach ($this->input('items', []) as $index => $row) {
            if ($validator->errors()->hasAny(["items.{$index}.quantity", "items.{$index}.purchase_price", "items.{$index}.discount", "items.{$index}.tax"])) {
                continue;
            }

            $line = $calculator->line($row);

            if (bccomp($line['gross'], self::MAX_TOTAL, 2) > 0) {
                $validator->errors()->add("items.{$index}.quantity", __('This line is too large. Split it into smaller purchases.'));

                continue;
            }

            if (bccomp($line['discount'], $line['gross'], 2) > 0) {
                $validator->errors()->add("items.{$index}.discount", __('The discount cannot be more than the line amount (:amount).', [
                    'amount' => $line['gross'],
                ]));

                continue;
            }

            $total = bcadd($total, bcadd($line['gross'], $line['tax'], 2), 2);
        }

        if (bccomp($total, self::MAX_TOTAL, 2) > 0) {
            $validator->errors()->add('items', __('The purchase total is too large. Split it into smaller purchases.'));
        }
    }

    private function purchase(): ?Purchase
    {
        return $this->route('purchase');
    }
}
