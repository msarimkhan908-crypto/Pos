<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Rules\UniqueItemCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.edit');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'sku' => $this->filled('sku') ? trim((string) $this->input('sku')) : null,
            'barcode' => $this->filled('barcode') ? trim((string) $this->input('barcode')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $variantId = $this->variant()?->getKey();

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('product_variants')
                    ->where('product_id', $this->product()->getKey())
                    ->whereNull('deleted_at')
                    ->ignore($variantId),
            ],
            'sku' => ['nullable', 'string', 'max:64', 'regex:'.ProductRequest::CODE_PATTERN, new UniqueItemCode('sku', ignoreVariantId: $variantId)],
            'barcode' => ['nullable', 'string', 'max:64', 'regex:'.ProductRequest::CODE_PATTERN, new UniqueItemCode('barcode', ignoreVariantId: $variantId)],
            'purchase_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.ProductRequest::MAX_PRICE],
            'selling_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.ProductRequest::MAX_PRICE],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => __('This product already has a variant with this name.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function variantData(): array
    {
        // A blank SKU is generated on create; on update it keeps the existing SKU.
        $fields = ['name', 'barcode', 'purchase_price', 'selling_price', 'is_active'];

        if (filled($this->validated('sku')) || ! $this->variant()) {
            $fields[] = 'sku';
        }

        return [
            ...$this->safe()->only($fields),
            'sort_order' => (int) $this->validated('sort_order'),
        ];
    }

    private function product(): Product
    {
        return $this->route('product');
    }

    private function variant(): ?ProductVariant
    {
        return $this->route('variant');
    }
}
