<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
use App\Models\Product;
use App\Rules\ImageUpload;
use App\Rules\UniqueItemCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creates or updates a product. product_type decides the shape:
 *   simple   - one item with one price, SKU and barcode.
 *   variable - sold as variants; the "variants" rows are created, updated and removed in the same save.
 */
class ProductRequest extends FormRequest
{
    public const CODE_PATTERN = '/^[A-Za-z0-9._\-\/]+$/';

    public const MAX_PRICE = 9999999999.99;

    public const MAX_VARIANTS = 50;

    public const SIMPLE = 'simple';

    public const VARIABLE = 'variable';

    public function authorize(): bool
    {
        return $this->user()->can($this->product() ? 'products.edit' : 'products.create');
    }

    protected function prepareForValidation(): void
    {
        $variants = collect($this->input('variants', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                ...$row,
                'sku' => filled($row['sku'] ?? null) ? trim((string) $row['sku']) : null,
                'barcode' => filled($row['barcode'] ?? null) ? trim((string) $row['barcode']) : null,
                'is_active' => filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();

        $dealItems = collect($this->input('deal_items', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                ...$row,
                'quantity' => (int) ($row['quantity'] ?? 1),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ])
            ->values()
            ->all();

        $this->merge([
            'product_type' => $this->input('product_type') ?: $this->currentType(),
            'is_active' => $this->boolean('is_active'),
            'is_deal' => $this->boolean('is_deal'),
            'remove_image' => $this->boolean('remove_image'),
            'sku' => $this->filled('sku') ? trim((string) $this->input('sku')) : null,
            'barcode' => $this->filled('barcode') ? trim((string) $this->input('barcode')) : null,
            'variants' => $variants,
            'deal_items' => $dealItems,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->product()?->getKey();
        $isVariable = $this->input('product_type') === self::VARIABLE;

        $isDeal = $this->boolean('is_deal');

        $rules = [
            'product_type' => ['required', Rule::in([self::SIMPLE, self::VARIABLE])],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:64', 'regex:'.self::CODE_PATTERN, new UniqueItemCode('sku', $productId)],
            'barcode' => ['nullable', 'string', 'max:64', 'regex:'.self::CODE_PATTERN, new UniqueItemCode('barcode', $productId)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'kitchen_station_id' => ['nullable', 'integer', Rule::exists('kitchen_stations', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'purchase_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE],
            'selling_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE],
            'tax_rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'discount_value' => ['nullable', 'required_with:discount_type', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE],
            'is_active' => ['boolean'],
            'is_deal' => ['boolean'],
            'image' => ImageUpload::rules(),
            'remove_image' => ['boolean'],
            'modifier_groups' => ['nullable', 'array'],
            'modifier_groups.*' => ['integer', 'distinct', Rule::exists('modifier_groups', 'id')],
            'variants' => $isVariable
                ? ['required', 'array', 'min:1', 'max:'.self::MAX_VARIANTS]
                : ['nullable', 'array'],
            'deal_items' => $isDeal
                ? ['required', 'array', 'min:1']
                : ['nullable', 'array'],
            'deal_items.*.component_id' => $isDeal
                ? ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')]
                : ['nullable', 'integer'],
            'deal_items.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'deal_items.*.quantity' => ['required', 'integer', 'min:1'],
            'deal_items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        if (! $isVariable) {
            return $rules;
        }

        // Wildcard keys so "distinct" compares across rows.
        $rules['variants.*.name'] = ['required', 'string', 'max:100', 'distinct:ignore_case'];
        $rules['variants.*.selling_price'] = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE];
        $rules['variants.*.purchase_price'] = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE];
        $rules['variants.*.is_active'] = ['boolean'];

        // Codes held by this product's variants that are in the form will be reassigned in the
        // same save, so they don't count as taken. Clashes between rows are caught in after().
        $keptVariantIds = $this->product()
            ? $this->product()->variants()
                ->whereIn('id', collect($this->input('variants', []))->pluck('id')->filter()->map(fn ($id) => (int) $id))
                ->pluck('id')
                ->all()
            : [];

        // Per-row rules.
        foreach (array_keys($this->input('variants', [])) as $index) {
            $prefix = "variants.{$index}";

            $rules["{$prefix}.id"] = $this->product()
                ? ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $productId)->whereNull('deleted_at')]
                : ['prohibited'];
            $rules["{$prefix}.sku"] = ['nullable', 'string', 'max:64', 'regex:'.self::CODE_PATTERN, new UniqueItemCode('sku', ignoreVariantId: $keptVariantIds)];
            $rules["{$prefix}.barcode"] = ['nullable', 'string', 'max:64', 'regex:'.self::CODE_PATTERN, new UniqueItemCode('barcode', ignoreVariantId: $keptVariantIds)];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateDiscount($validator),
            fn (Validator $validator) => $this->validateCodesWithinForm($validator),
        ];
    }

    public function isDeal(): bool
    {
        return (bool) $this->validated('is_deal', false);
    }

    public function attributes(): array
    {
        return [
            'sku' => 'SKU',
            'category_id' => 'category',
            'brand_id' => 'brand',
            'modifier_groups.*' => 'modifier group',
            'variants.*.name' => 'variant name',
            'variants.*.sku' => 'variant SKU',
            'variants.*.barcode' => 'variant barcode',
            'variants.*.selling_price' => 'variant price',
            'variants.*.purchase_price' => 'variant cost',
        ];
    }

    public function messages(): array
    {
        return [
            ...ImageUpload::messages('image'),
            'sku.regex' => __('The SKU may only contain letters, numbers, dots, dashes, underscores and slashes.'),
            'barcode.regex' => __('The barcode may only contain letters, numbers, dots, dashes, underscores and slashes.'),
            'variants.*.sku.regex' => __('The SKU may only contain letters, numbers, dots, dashes, underscores and slashes.'),
            'variants.*.barcode.regex' => __('The barcode may only contain letters, numbers, dots, dashes, underscores and slashes.'),
            'variants.required' => __('A variant product needs at least one variant.'),
            'variants.*.name.distinct' => __('Each variant needs a different name.'),
            'deal_items.required' => __('A deal product needs at least one component.'),
            'deal_items.min' => __('A deal product needs at least one component.'),
            'deal_items.*.component_id.required' => __('Select a product for each deal component.'),
            'deal_items.*.component_id.exists' => __('The selected component product is not available.'),
            'deal_items.*.quantity.min' => __('Each component must have a quantity of at least 1.'),
        ];
    }

    public function isVariable(): bool
    {
        return $this->validated('product_type') === self::VARIABLE;
    }

    /**
     * Attributes to save, with defaults for optional numbers. Excludes the image.
     *
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        $discountType = $this->validated('discount_type');

        // A blank SKU is generated on create; on update it keeps the existing SKU.
        $fields = ['name', 'barcode', 'category_id', 'brand_id', 'kitchen_station_id', 'description', 'selling_price', 'is_active', 'is_deal'];

        if (filled($this->validated('sku')) || ! $this->product()) {
            $fields[] = 'sku';
        }

        return [
            ...$this->safe()->only($fields),
            'purchase_price' => $this->validated('purchase_price') ?? 0,
            'tax_rate' => $this->validated('tax_rate') ?? 0,
            'discount_type' => $discountType,
            'discount_value' => $discountType ? $this->validated('discount_value') : 0,
        ];
    }

    /**
     * Validated deal component rows (empty for non-deal products).
     *
     * @return list<array<string, mixed>>
     */
    public function dealItemRows(): array
    {
        return $this->isDeal() ? array_values($this->validated('deal_items', [])) : [];
    }

    /**
     * Validated variant rows (empty for simple products), in submitted order.
     *
     * @return list<array<string, mixed>>
     */
    public function variantRows(): array
    {
        return $this->isVariable() ? array_values($this->validated('variants', [])) : [];
    }

    /**
     * Modifier group ids keyed for sync(), keeping the submitted order.
     *
     * @return array<int, array{sort_order: int}>
     */
    public function modifierGroupSync(): array
    {
        return collect($this->validated('modifier_groups', []))
            ->values()
            ->mapWithKeys(fn ($id, $index) => [(int) $id => ['sort_order' => $index]])
            ->all();
    }

    private function validateDiscount(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['discount_type', 'discount_value', 'selling_price'])) {
            return;
        }

        $type = DiscountType::tryFrom((string) $this->input('discount_type'));
        $value = (float) $this->input('discount_value');

        if ($type === DiscountType::Percent && $value > 100) {
            $validator->errors()->add('discount_value', __('A percentage discount cannot exceed 100%.'));
        }

        if ($type === DiscountType::Fixed && $value > (float) $this->input('selling_price')) {
            $validator->errors()->add('discount_value', __('A fixed discount cannot exceed the selling price.'));
        }
    }

    /**
     * Codes typed in this form are not in the database yet, so check them against each other.
     */
    private function validateCodesWithinForm(Validator $validator): void
    {
        if ($this->input('product_type') !== self::VARIABLE) {
            return;
        }

        foreach (['sku', 'barcode'] as $column) {
            $seen = array_filter([mb_strtolower((string) $this->input($column)) => 'product']);

            foreach ($this->input('variants', []) as $index => $row) {
                $code = mb_strtolower((string) ($row[$column] ?? ''));

                if ($code === '') {
                    continue;
                }

                if (isset($seen[$code])) {
                    $validator->errors()->add("variants.{$index}.{$column}", __('This :field is already used in this form.', ['field' => $column === 'sku' ? 'SKU' : 'barcode']));
                }

                $seen[$code] = true;
            }
        }
    }

    private function currentType(): string
    {
        return $this->product()?->hasVariants() ? self::VARIABLE : self::SIMPLE;
    }

    private function product(): ?Product
    {
        return $this->route('product');
    }
}
