<?php

namespace App\Services;

use App\Enums\DiscountType;

/**
 * Server-side sale maths with exact decimal strings (bcmath), never floats.
 *
 * Per line:  gross    = quantity x unit_price
 *            discount = the line's discount (fixed amount for the whole line, or % of gross)
 *            total    = gross - discount
 * Order:     order_discount = fixed amount, or % of sum(line totals); it is shared across
 *            lines in proportion to their totals (to the cent, largest remainder first)
 * Tax:       per line, (total - order discount share) x the product's tax rate
 * Sale:      subtotal = sum(gross), discount = item discounts + order discount,
 *            tax = sum(line tax), total = subtotal - discount + tax
 *
 * Every rounding is to 2 dp, half up. Discounts are clamped so nothing goes negative;
 * CartResolver rejects discounts that would need clamping before they get here.
 */
class SaleCalculator
{
    private const SCALE = 8;

    /**
     * @param  list<array<string, mixed>>  $lines  each with unit_price, quantity, tax_rate and optional discount_type / discount_value
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     subtotal: string, item_discount: string, order_discount: string,
     *     discount: string, tax: string, total: string,
     * }
     */
    public function calculate(array $lines, ?DiscountType $orderDiscountType = null, string|int|float|null $orderDiscountValue = null): array
    {
        $subtotal = $itemDiscount = $base = '0.00';

        foreach ($lines as $i => $line) {
            $lines[$i] = $line = $this->line($line);

            $subtotal = bcadd($subtotal, $line['gross'], 2);
            $itemDiscount = bcadd($itemDiscount, $line['discount'], 2);
            $base = bcadd($base, $line['total'], 2);
        }

        $orderDiscount = $this->discount($base, $orderDiscountType, $orderDiscountValue);
        $shares = $this->allocate($orderDiscount, array_column($lines, 'total'));
        $tax = '0.00';

        foreach ($lines as $i => $line) {
            $taxable = bcsub($line['total'], $shares[$i], 2);
            $lineTax = $this->round(bcdiv(bcmul($taxable, $line['tax_rate'], self::SCALE), '100', self::SCALE));

            $lines[$i] += ['order_discount' => $shares[$i], 'taxable' => $taxable, 'tax' => $lineTax];
            $tax = bcadd($tax, $lineTax, 2);
        }

        $discount = bcadd($itemDiscount, $orderDiscount, 2);

        return [
            'lines' => array_values($lines),
            'subtotal' => $subtotal,
            'item_discount' => $itemDiscount,
            'order_discount' => $orderDiscount,
            'discount' => $discount,
            'tax' => $tax,
            'total' => bcadd(bcsub($subtotal, $discount, 2), $tax, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed> the line with normalised values plus gross, discount and total
     */
    public function line(array $line): array
    {
        $price = $this->normalise($line['unit_price'] ?? '0');
        $quantity = (string) max(0, (int) ($line['quantity'] ?? 0));
        $gross = bcmul($price, $quantity, 2);
        $discount = $this->discount($gross, $line['discount_type'] ?? null, $line['discount_value'] ?? null);

        return [
            ...$line,
            'unit_price' => $price,
            'quantity' => (int) $quantity,
            'tax_rate' => $this->normalise($line['tax_rate'] ?? '0'),
            'gross' => $gross,
            'discount' => $discount,
            'total' => bcsub($gross, $discount, 2),
        ];
    }

    /**
     * Discount amount on $amount, never more than $amount.
     */
    public function discount(string $amount, DiscountType|string|null $type, string|int|float|null $value): string
    {
        $type = is_string($type) ? DiscountType::tryFrom($type) : $type;
        $value = $this->normalise($value);

        if ($type === null || bccomp($value, '0', 2) <= 0) {
            return '0.00';
        }

        $discount = $type === DiscountType::Percent
            ? $this->round(bcdiv(bcmul($amount, $value, self::SCALE), '100', self::SCALE))
            : $value;

        return bccomp($discount, $amount, 2) > 0 ? bcadd($amount, '0', 2) : $discount;
    }

    /**
     * Split $amount across $weights proportionally, to the cent. Each share is floored,
     * then leftover cents go to the largest remainders, so shares always add up to
     * $amount and never exceed their weight.
     *
     * @param  list<string>  $weights
     * @return list<string>
     */
    public function allocate(string $amount, array $weights): array
    {
        $total = array_reduce($weights, fn (string $sum, string $w) => bcadd($sum, $w, 2), '0.00');
        $shares = array_fill(0, count($weights), '0.00');

        if (bccomp($amount, '0', 2) <= 0 || bccomp($total, '0', 2) <= 0) {
            return $shares;
        }

        $remainders = [];

        foreach ($weights as $i => $weight) {
            $exact = bcdiv(bcmul($amount, $weight, self::SCALE), $total, self::SCALE);
            $shares[$i] = bcadd($exact, '0', 2); // truncates = floor for non-negative values
            $remainders[$i] = bcsub($exact, $shares[$i], self::SCALE);
        }

        $leftoverCents = (int) bcmul(bcsub($amount, array_reduce($shares, fn (string $s, string $v) => bcadd($s, $v, 2), '0.00'), 2), '100', 0);

        // Largest remainder first; ties go to the earlier line.
        uksort($remainders, fn (int $a, int $b) => bccomp($remainders[$b], $remainders[$a], self::SCALE) ?: $a <=> $b);

        foreach (array_keys($remainders) as $i) {
            if ($leftoverCents-- <= 0) {
                break;
            }
            $shares[$i] = bcadd($shares[$i], '0.01', 2);
        }

        return $shares;
    }

    /**
     * Round a non-negative decimal string to 2 places, half up.
     */
    private function round(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    private function normalise(string|int|float|null $value): string
    {
        $value = trim((string) ($value ?? '0'));

        if ($value === '' || ! is_numeric($value) || bccomp($value, '0', self::SCALE) < 0) {
            return '0.00';
        }

        return bcadd($value, '0', 2);
    }
}
