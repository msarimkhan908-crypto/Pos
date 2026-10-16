<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A preparation area (Main Kitchen, Grill, Pizza, Drinks, Dessert…). A product is sent to
 * its own station, else its category's. Products with neither are not sent to the kitchen
 * (e.g. retail items), so a sale without kitchen items creates no KOT.
 */
#[Fillable(['name', 'description', 'is_active', 'sort_order'])]
class KitchenStation extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<KitchenOrderItem, $this>
     */
    public function kotItems(): HasMany
    {
        return $this->hasMany(KitchenOrderItem::class);
    }

    /**
     * Options for a station <select>, e.g. on the category and product forms.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::query()->ordered()->get()
            ->mapWithKeys(fn (self $s) => [$s->id => $s->name.($s->is_active ? '' : ' ('.__('inactive').')')])
            ->all();
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
