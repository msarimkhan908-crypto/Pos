<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One cart line of a sale, with names and prices snapshotted at the time of sale.
 *
 * unit_price includes modifiers; total = quantity x unit_price - discount (the line's own
 * discount). order_discount is this line's share of the order discount, and tax is
 * charged on total - order_discount.
 */
class SaleItem extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'parent_sale_item_id' => 'integer',
            'unit_price' => 'decimal:2',
            'modifiers_total' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'discount' => 'decimal:2',
            'order_discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }

    /**
     * The parent deal sale item (null for regular items and deal parents).
     *
     * @return BelongsTo<SaleItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'parent_sale_item_id');
    }

    /**
     * The component lines that belong to this deal parent line.
     *
     * @return HasMany<SaleItem, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'parent_sale_item_id');
    }

    /**
     * @return HasMany<SaleItemModifier, $this>
     */
    public function modifiers(): HasMany
    {
        return $this->hasMany(SaleItemModifier::class)->orderBy('id');
    }

    /**
     * @return HasMany<ReturnItem, $this>
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    /**
     * Units that can still be returned (pending and completed returns already count).
     */
    public function returnableQuantity(): int
    {
        return max(0, $this->quantity - $this->returned_quantity);
    }

    /**
     * What the customer actually paid for this line: after the line discount and its share
     * of the order discount, including tax. Refunds never exceed this.
     */
    public function paidAmount(): string
    {
        return bcadd(bcsub((string) $this->total, (string) $this->order_discount, 2), (string) $this->tax, 2);
    }

    public function displayName(): string
    {
        return $this->variant_name ? "{$this->product_name} ({$this->variant_name})" : $this->product_name;
    }
}
