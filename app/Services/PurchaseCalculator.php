<?php

namespace App\Services;

/**
 * Server-side purchase maths with exact decimal strings (bcmath), never floats.
 *
 * Per line:  gross      = quantity x purchase_price   (rounded to 2 dp, half up)
 *            line_total = gross - discount + tax
 * Purchase:  subtotal = sum(gross), discount = sum(line discount), tax = sum(line tax),
 *            total = subtotal - discount + tax  (= sum of line totals)
 */
class PurchaseCalculator
{
    private const SCALE = 6;

    /**
     * @param  iterable<array{quantity: string|int|float, purchase_price: string|int|float, discount?: string|int|float|null, tax?: string|int|float|null}>  $items
     * @return array{
     *     items: list<array<string, mixed>>,
     *     subtotal: string, discount: string, tax: string, total: string,
     * }
     */
    public function calculate(iterable $items): array
    {
        $subtotal = $discount = $tax = '0';
        $lines = [];

        foreach ($items as $item) {
            $line = $this->line($item);

            $subtotal = bcadd($subtotal, $line['gross'], 2);
            $discount = bcadd($discount, $line['discount'], 2);
            $tax = bcadd($tax, $line['tax'], 2);

            $lines[] = $line;
        }

        return [
            'items' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => bcadd(bcsub($subtotal, $discount, 2), $tax, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed> the item with normalised quantity, prices, gross and line_total
     */
    public function line(array $item): array
    {
        $quantity = $this->normalise($item['quantity'] ?? '0', 3);
        $price = $this->normalise($item['purchase_price'] ?? '0', 2);
        $discount = $this->normalise($item['discount'] ?? '0', 2);
        $tax = $this->normalise($item['tax'] ?? '0', 2);

        $gross = $this->round(bcmul($quantity, $price, self::SCALE));

        return [
            ...$item,
            'quantity' => $quantity,
            'purchase_price' => $price,
            'discount' => $discount,
            'tax' => $tax,
            'gross' => $gross,
            'line_total' => bcadd(bcsub($gross, $discount, 2), $tax, 2),
        ];
    }

    /**
     * Round a non-negative decimal string to 2 places, half up.
     */
    private function round(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    private function normalise(string|int|float|null $value, int $scale): string
    {
        $value = trim((string) ($value ?? '0'));

        if ($value === '' || ! is_numeric($value)) {
            return bcadd('0', '0', $scale);
        }

        // Validated input is plain decimal; bcadd normalises format and scale.
        return bcadd($value, '0', $scale);
    }
}
