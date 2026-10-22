<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasSlug;
use App\Support\CategoryIcon;
use App\Support\ImageStorage;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'image_path', 'is_active', 'sort_order', 'kitchen_station_id'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToOrganization, HasFactory, HasSlug;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Where this category's items are prepared (products can override it).
     *
     * @return BelongsTo<KitchenStation, $this>
     */
    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => ImageStorage::url($this->image_path));
    }

    protected function placeholderColor(): Attribute
    {
        return Attribute::get(fn () => CategoryIcon::colorForId((int) ($this->id ?? 0)));
    }

    protected function placeholderIcon(): Attribute
    {
        return Attribute::get(fn () => CategoryIcon::forName($this->name ?? ''));
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

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%");
        }));
    }
}
