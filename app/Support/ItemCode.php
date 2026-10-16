<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SKUs and barcodes identify a single sellable item, so each value must be unique
 * across products AND product variants (including soft-deleted rows, which keep
 * their codes so history stays unambiguous).
 */
class ItemCode
{
    /**
     * @param  'sku'|'barcode'  $column
     * @param  int|list<int>|null  $ignoreVariantId  one or more variants whose current code does not count
     */
    public static function isTaken(string $column, string $value, ?int $ignoreProductId = null, int|array|null $ignoreVariantId = null): bool
    {
        $ignoreVariantIds = array_filter((array) $ignoreVariantId);

        $inProducts = DB::table('products')
            ->where($column, $value)
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->exists();

        if ($inProducts) {
            return true;
        }

        return DB::table('product_variants')
            ->where($column, $value)
            ->when($ignoreVariantIds, fn ($query) => $query->whereNotIn('id', $ignoreVariantIds))
            ->exists();
    }

    /**
     * Generate an unused SKU, optionally derived from a prefix.
     */
    public static function uniqueSku(?string $prefix = null): string
    {
        $prefix = $prefix !== null ? Str::upper(Str::limit($prefix, 50, '')) : 'SKU';

        do {
            $sku = $prefix.'-'.Str::upper(Str::random(6));
        } while (self::isTaken('sku', $sku));

        return $sku;
    }
}
