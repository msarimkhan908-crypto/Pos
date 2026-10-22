<?php

namespace App\Models;

use App\Enums\KitchenStatus;
use App\Enums\TableStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dining table. A Dine In order marks it Occupied (App\Services\SaleService); it becomes
 * Available again when its last open kitchen order is Completed (App\Services\KitchenService),
 * or when staff change the status by hand. Reserved tables can't take new orders.
 */
#[Fillable(['name', 'section', 'capacity', 'status', 'is_active', 'sort_order'])]
class RestaurantTable extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $attributes = [
        'status' => 'available',
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => TableStatus::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'table_id');
    }

    /**
     * @return HasMany<KitchenOrder, $this>
     */
    public function kitchenOrders(): HasMany
    {
        return $this->hasMany(KitchenOrder::class, 'table_id');
    }

    /**
     * Tickets still in the kitchen for this table.
     *
     * @return HasMany<KitchenOrder, $this>
     */
    public function openKitchenOrders(): HasMany
    {
        return $this->kitchenOrders()->whereIn('status', KitchenStatus::active());
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
