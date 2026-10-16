<?php

namespace App\Rules;

use App\Support\ItemCode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A SKU or barcode must not be used by any other product or variant.
 */
class UniqueItemCode implements ValidationRule
{
    /**
     * @param  'sku'|'barcode'  $column
     * @param  int|list<int>|null  $ignoreVariantId
     */
    public function __construct(
        private string $column,
        private ?int $ignoreProductId = null,
        private int|array|null $ignoreVariantId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (ItemCode::isTaken($this->column, (string) $value, $this->ignoreProductId, $this->ignoreVariantId)) {
            $fail(__('This :attribute is already used by another product or variant (including deleted ones).'));
        }
    }
}
