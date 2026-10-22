<?php

namespace App\Models;

use App\Enums\KitchenStatus;
use App\Enums\OrderType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kitchen order ticket (KOT), created by App\Services\KitchenService when a sale with
 * kitchen items is completed. Its status follows its items (see KitchenService::refresh()).
 */
class KitchenOrder extends Model
{
    public const NUMBER_PREFIX = 'KOT-';

    use BelongsToOrganization;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => KitchenStatus::class,
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Build the KOT display string from an order_no (no zero-padding; resets daily).
     *
     * e.g. order_no=28 with default prefix → "KOT-28"
     */
    public static function numberFor(int $orderNo): string
    {
        return (settings('general.kot_prefix') ?? self::NUMBER_PREFIX).(string) $orderNo;
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<KitchenOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(KitchenOrderItem::class)->orderBy('id');
    }

    /**
     * Items grouped by station name for the printed ticket, optionally for one station only.
     *
     * @return Collection<string, Collection<int, KitchenOrderItem>>
     */
    public function itemsByStation(?int $stationId = null): Collection
    {
        return $this->items
            ->when($stationId, fn (Collection $items) => $items->where('kitchen_station_id', $stationId))
            ->groupBy(fn (KitchenOrderItem $item) => $item->station?->name ?? __('Kitchen'));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, KitchenStatus::active(), true);
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', KitchenStatus::active());
    }
}
