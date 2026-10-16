<?php

namespace App\Models;

use App\Enums\KitchenStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on a KOT (table `kot_items`). Names, variant and add-ons are read from the
 * sale line it points at; the item keeps its own station, note and kitchen status.
 */
class KitchenOrderItem extends Model
{
    protected $table = 'kot_items';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => KitchenStatus::class,
        ];
    }

    /**
     * @return BelongsTo<KitchenOrder, $this>
     */
    public function kitchenOrder(): BelongsTo
    {
        return $this->belongsTo(KitchenOrder::class);
    }

    /**
     * @return BelongsTo<SaleItem, $this>
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
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
     * @return BelongsTo<KitchenStation, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'kitchen_station_id');
    }
}
