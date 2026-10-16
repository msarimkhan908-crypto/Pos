<?php

namespace App\Models;

use App\Support\ItemCode;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A sellable option of a product (e.g. Small / Large). Null prices inherit the parent product's price.
 */
#[Fillable(['name', 'sku', 'barcode', 'purchase_price', 'selling_price', 'is_active', 'sort_order'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (ProductVariant $variant) {
            if (blank($variant->sku)) {
                $base = Str::upper($variant->product->sku.'-'.Str::slug($variant->name));
                $variant->sku = ItemCode::isTaken('sku', $base) ? ItemCode::uniqueSku($base) : $base;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function effectiveSellingPrice(): float
    {
        return (float) ($this->selling_price ?? $this->product->selling_price);
    }

    public function effectivePurchasePrice(): float
    {
        return (float) ($this->purchase_price ?? $this->product->purchase_price);
    }
}
