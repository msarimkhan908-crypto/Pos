<?php

namespace App\Models;

use Database\Factories\ModifierGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A set of add-ons attached to products, e.g. "Burger Add-ons" (Cheese +100, Egg +50).
 * min_select / max_select tell the POS how many options may be chosen (max null = no limit).
 */
#[Fillable(['name', 'min_select', 'max_select', 'is_active', 'sort_order'])]
class ModifierGroup extends Model
{
    /** @use HasFactory<ModifierGroupFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'min_select' => 'integer',
            'max_select' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Modifier, $this>
     */
    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('sort_order');
    }

    public function isRequired(): bool
    {
        return $this->min_select > 0;
    }

    /**
     * Human-readable selection rule, e.g. "Optional · up to 3" or "Required · choose 1".
     */
    public function selectionRule(): string
    {
        $min = $this->min_select;
        $max = $this->max_select;

        $range = match (true) {
            $max === null && $min === 0 => __('any number'),
            $max === null => __('at least :min', ['min' => $min]),
            $min === $max => __('choose :count', ['count' => $max]),
            $min === 0 => __('up to :max', ['max' => $max]),
            default => __(':min to :max', ['min' => $min, 'max' => $max]),
        };

        return ($this->isRequired() ? __('Required') : __('Optional')).' · '.$range;
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
