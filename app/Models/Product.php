<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\CategoryIcon;
use App\Support\ImageStorage;
use App\Support\ItemCode;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'brand_id', 'kitchen_station_id', 'name', 'sku', 'barcode', 'description', 'image_path',
    'purchase_price', 'selling_price', 'tax_rate', 'discount_type', 'discount_value',
    'is_active', 'is_deal', 'sort_order',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (blank($product->sku)) {
                $product->sku = ItemCode::uniqueSku();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'is_active' => 'boolean',
            'is_deal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Where this product is prepared. Null = use the category's station.
     *
     * @return BelongsTo<KitchenStation, $this>
     */
    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<DealItem, $this>
     */
    public function dealItems(): HasMany
    {
        return $this->hasMany(DealItem::class)->orderBy('sort_order');
    }

    /**
     * Component products included in this deal.
     *
     * @return HasManyThrough<Product, DealItem, $this>
     */
    public function components(): HasManyThrough
    {
        return $this->hasManyThrough(Product::class, DealItem::class, 'product_id', 'id', 'id', 'component_id');
    }

    /**
     * @return BelongsToMany<ModifierGroup, $this>
     */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->orderBy('modifier_groups.name');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => ImageStorage::url($this->image_path));
    }

    protected function placeholderColor(): Attribute
    {
        return Attribute::get(fn () => CategoryIcon::colorForId((int) ($this->category_id ?? $this->id ?? 0)));
    }

    protected function placeholderIcon(): Attribute
    {
        $name = $this->category?->name ?? $this->name ?? '';

        return Attribute::get(fn () => CategoryIcon::forName($name));
    }

    public function hasVariants(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }

    /**
     * Selling price after the product discount.
     */
    public function discountedPrice(?float $price = null): float
    {
        $price ??= (float) $this->selling_price;

        return $this->discount_type
            ? $this->discount_type->apply($price, (float) $this->discount_value)
            : round($price, 2);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('products.is_active', true);
    }

    #[Scope]
    protected function deals(Builder $query): void
    {
        $query->where('products.is_deal', true);
    }

    /**
     * Match name, SKU or barcode, including the SKU/barcode of any variant.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('products.name', 'like', "%{$term}%")
                ->orWhere('products.sku', 'like', "%{$term}%")
                ->orWhere('products.barcode', $term)
                ->orWhereHas('variants', fn (Builder $query) => $query
                    ->where('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', $term));
        }));
    }
}
