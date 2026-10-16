<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\DealItem;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns cart rows sent by the POS (product id, variant id, modifier ids, quantity,
 * line discount) into priced lines. Prices, names and tax rates always come from
 * the database, never from the browser.
 *
 * A product is sellable when it is active, not archived, and its category (if any) is active.
 * Products with variants must be sold as one of their active variants. Modifiers must
 * belong to an active group attached to the product and respect the group's min / max.
 */
class CartResolver
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>> priced lines, in the same order as $items (with deal components expanded inline)
     *
     * @throws ValidationException with keys like items.2.variant_id
     */
    public function resolve(array $items): array
    {
        [$lines, $errors] = $this->resolveEach($items);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $lines;
    }

    /**
     * Resolve what can be resolved and report the rest (used when resuming a held sale,
     * where a product may have been archived since).
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, string>} [lines keyed by row index, errors]
     */
    public function resolveEach(array $items): array
    {
        $products = $this->products(array_column($items, 'product_id'));
        $lines = $errors = [];

        foreach (array_values($items) as $i => $item) {
            try {
                $line = $this->line($item, $products->get((int) ($item['product_id'] ?? 0)));
                $lines[$i] = $line;

                // Expand deal components as extra zero-price lines after the deal parent.
                // We tag the parent with its original cart item index so SaleService can
                // look up the saved parent SaleItem when it encounters a component line.
                if ($line['is_deal'] ?? false) {
                    $lines[$i]['_cart_item_index'] = $i;
                    $product = $products->get((int) ($item['product_id'] ?? 0));

                    foreach ($product->dealItems as $dealItem) {
                        $component = $dealItem->component;
                        $variant = $dealItem->variant;

                        if (! $component) {
                            continue;
                        }

                        $lines[] = $this->dealComponentLine($line, $i, $dealItem, $component, $variant);
                    }
                }
            } catch (CartLineException $e) {
                $errors["items.{$i}.{$e->field}"] = $e->getMessage();
            }
        }

        return [array_values($lines), $errors];
    }

    /**
     * Sellable products with everything the POS needs, keyed by id.
     *
     * @param  array<int, mixed>  $ids
     * @return Collection<int, Product>
     */
    public function products(array $ids): Collection
    {
        return static::sellable()
            ->whereKey(array_unique(array_map('intval', $ids)))
            ->with(static::eagerLoads())
            ->withCount('variants')
            ->get()
            ->keyBy('id');
    }

    /**
     * @return Builder<Product>
     */
    public static function sellable(): Builder
    {
        return Product::query()
            ->active()
            ->where(fn (Builder $query) => $query
                ->whereNull('products.category_id')
                ->orWhereHas('category', fn (Builder $query) => $query->where('is_active', true)));
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function eagerLoads(): array
    {
        return [
            'variants' => fn ($query) => $query->where('is_active', true),
            'modifierGroups' => fn ($query) => $query->where('modifier_groups.is_active', true),
            'modifierGroups.modifiers' => fn ($query) => $query->where('is_active', true),
            'kitchenStation:id,name,is_active',
            'category:id,kitchen_station_id',
            'category.kitchenStation:id,name,is_active',
            'dealItems.component.kitchenStation:id,name,is_active',
            'dealItems.component.category:id,kitchen_station_id',
            'dealItems.component.category.kitchenStation:id,name,is_active',
            'dealItems.variant:id,name',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     *
     * @throws CartLineException
     */
    private function line(array $item, ?Product $product): array
    {
        if (! $product) {
            throw new CartLineException('product_id', __('This product is no longer available.'));
        }

        $variant = $this->variant($product, $item['variant_id'] ?? null);
        $modifiers = $this->modifiers($product, (array) ($item['modifier_ids'] ?? []));

        $listPrice = $variant ? $variant->effectiveSellingPrice() : (float) $product->selling_price;
        $basePrice = number_format($product->discountedPrice($listPrice), 2, '.', '');
        $modifiersTotal = $modifiers->reduce(fn (string $sum, array $m) => bcadd($sum, $m['price'], 2), '0.00');

        $line = [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'product_name' => $product->name,
            'variant_name' => $variant?->name,
            'sku' => $variant?->sku ?? $product->sku,
            'quantity' => (int) $item['quantity'],
            'base_price' => $basePrice,
            'modifiers' => $modifiers->all(),
            'modifier_ids' => $modifiers->pluck('modifier_id')->all(),
            'modifiers_total' => $modifiersTotal,
            'unit_price' => bcadd($basePrice, $modifiersTotal, 2),
            'tax_rate' => (string) $product->tax_rate,
            'discount_type' => DiscountType::tryFrom((string) ($item['discount_type'] ?? '')),
            'discount_value' => (string) ($item['discount_value'] ?? '0') ?: '0',
            'image_url' => $product->image_url,
            'note' => filled($item['note'] ?? null) ? trim((string) $item['note']) : null,
            'is_deal' => (bool) $product->is_deal,
            'is_deal_component' => false,
            'deal_line_index' => null,
            ...$this->station($product),
        ];

        $this->checkDiscount($line);

        return $line;
    }

    /**
     * Build a zero-price component line for a deal item.
     *
     * @param  array<string, mixed>  $parentLine
     * @return array<string, mixed>
     */
    private function dealComponentLine(array $parentLine, int $dealLineIndex, DealItem $dealItem, Product $component, ?ProductVariant $variant): array
    {
        return [
            'product_id' => $component->id,
            'variant_id' => $variant?->id,
            'product_name' => $component->name,
            'variant_name' => $variant?->name,
            'sku' => $variant?->sku ?? $component->sku,
            'quantity' => $dealItem->quantity * $parentLine['quantity'],
            'base_price' => '0.00',
            'modifiers' => [],
            'modifier_ids' => [],
            'modifiers_total' => '0.00',
            'unit_price' => '0.00',
            'tax_rate' => '0.00',
            'discount_type' => null,
            'discount_value' => '0',
            'image_url' => $component->image_url,
            'note' => null,
            'is_deal' => false,
            'is_deal_component' => true,
            'deal_line_index' => $dealLineIndex,
            ...$this->station($component),
        ];
    }

    /**
     * Kitchen station for a product: its own (if active), else its category's (if active), else none.
     *
     * @return array{kitchen_station_id: ?int, kitchen_station_name: ?string}
     */
    private function station(Product $product): array
    {
        $station = collect([$product->kitchenStation, $product->category?->kitchenStation])
            ->first(fn ($station) => $station?->is_active);

        return ['kitchen_station_id' => $station?->id, 'kitchen_station_name' => $station?->name];
    }

    /**
     * @throws CartLineException
     */
    private function variant(Product $product, mixed $variantId): ?ProductVariant
    {
        // Any non-archived variant (active or not) makes this a variant product.
        if (! $product->variants_count) {
            if (filled($variantId)) {
                throw new CartLineException('variant_id', __('This product has no variants.'));
            }

            return null;
        }

        if (blank($variantId)) {
            throw new CartLineException('variant_id', __('Choose a variant of :product.', ['product' => $product->name]));
        }

        return $product->variants->firstWhere('id', (int) $variantId)
            ?? throw new CartLineException('variant_id', __('This variant is not available.'));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return \Illuminate\Support\Collection<int, array{modifier_id: int, group_name: string, name: string, price: string}>
     *
     * @throws CartLineException
     */
    private function modifiers(Product $product, array $ids): \Illuminate\Support\Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $chosen = collect();

        /** @var ModifierGroup $group */
        foreach ($product->modifierGroups as $group) {
            $picked = $group->modifiers->whereIn('id', $ids);
            $count = $picked->count();

            if ($count < $group->min_select || ($group->max_select !== null && $count > $group->max_select)) {
                throw new CartLineException('modifier_ids', __(':group: :rule.', ['group' => $group->name, 'rule' => $group->selectionRule()]));
            }

            $chosen = $chosen->merge($picked->map(fn (Modifier $m) => [
                'modifier_id' => $m->id,
                'group_name' => $group->name,
                'name' => $m->name,
                'price' => (string) $m->price,
            ]));
        }

        if ($chosen->count() !== count($ids)) {
            throw new CartLineException('modifier_ids', __('An add-on is not available for this product.'));
        }

        return $chosen->values();
    }

    /**
     * @param  array<string, mixed>  $line
     *
     * @throws CartLineException
     */
    private function checkDiscount(array $line): void
    {
        $value = $line['discount_value'];

        if ($line['discount_type'] === DiscountType::Percent && bccomp($value, '100', 2) > 0) {
            throw new CartLineException('discount_value', __('A percentage discount cannot be more than 100%.'));
        }

        $gross = bcmul($line['unit_price'], (string) $line['quantity'], 2);

        if ($line['discount_type'] === DiscountType::Fixed && bccomp($value, $gross, 2) > 0) {
            throw new CartLineException('discount_value', __('The discount cannot be more than the line amount (:amount).', ['amount' => $gross]));
        }
    }
}
