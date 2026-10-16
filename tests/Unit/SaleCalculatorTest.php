<?php

use App\Enums\DiscountType;
use App\Services\SaleCalculator;

/**
 * @return array<string, mixed>
 */
function saleLine(string $price, int $quantity, string $taxRate = '0', ?DiscountType $type = null, string $value = '0'): array
{
    return ['unit_price' => $price, 'quantity' => $quantity, 'tax_rate' => $taxRate, 'discount_type' => $type, 'discount_value' => $value];
}

test('the reference sale: order discount before tax', function () {
    $result = (new SaleCalculator)->calculate([
        saleLine('500', 2, '18'),  // Burger
        saleLine('200', 1, '18'),  // Coke
        saleLine('300', 1, '18'),  // Fries
    ], DiscountType::Fixed, '100');

    expect($result)
        ->subtotal->toBe('1500.00')
        ->item_discount->toBe('0.00')
        ->order_discount->toBe('100.00')
        ->discount->toBe('100.00')
        ->tax->toBe('252.00')
        ->total->toBe('1652.00');

    expect(array_column($result['lines'], 'total'))->toBe(['1000.00', '200.00', '300.00']);
    expect(array_column($result['lines'], 'order_discount'))->toBe(['66.67', '13.33', '20.00']);
});

test('line discounts can be fixed or a percentage', function () {
    $result = (new SaleCalculator)->calculate([
        saleLine('500', 1, '0', DiscountType::Fixed, '50'),
        saleLine('200', 2, '0', DiscountType::Percent, '10'),
    ]);

    expect(array_column($result['lines'], 'discount'))->toBe(['50.00', '40.00'])
        ->and(array_column($result['lines'], 'total'))->toBe(['450.00', '360.00'])
        ->and($result['subtotal'])->toBe('900.00')
        ->and($result['discount'])->toBe('90.00')
        ->and($result['total'])->toBe('810.00');
});

test('a percentage order discount applies to the amount after item discounts', function () {
    $result = (new SaleCalculator)->calculate([
        saleLine('1000', 1, '0', DiscountType::Fixed, '200'),
    ], DiscountType::Percent, '10');

    expect($result['order_discount'])->toBe('80.00')
        ->and($result['discount'])->toBe('280.00')
        ->and($result['total'])->toBe('720.00');
});

test('discounts never make a line or the sale negative', function () {
    $result = (new SaleCalculator)->calculate([
        saleLine('100', 1, '10', DiscountType::Fixed, '250'),
        saleLine('100', 1, '10', DiscountType::Percent, '150'),
    ], DiscountType::Fixed, '999');

    expect(array_column($result['lines'], 'total'))->toBe(['0.00', '0.00'])
        ->and($result['order_discount'])->toBe('0.00')
        ->and($result['tax'])->toBe('0.00')
        ->and($result['total'])->toBe('0.00');
});

test('each line is taxed at its own product rate', function () {
    $result = (new SaleCalculator)->calculate([
        saleLine('100', 1, '18'),
        saleLine('100', 1, '5'),
        saleLine('100', 1, '0'),
    ]);

    expect(array_column($result['lines'], 'tax'))->toBe(['18.00', '5.00', '0.00'])
        ->and($result['total'])->toBe('323.00');
});

test('order discount shares always add up exactly and never exceed a line', function () {
    $calculator = new SaleCalculator;

    // 0.02 over four equal lines: naive rounding would give 0.01 x 4 = 0.04.
    expect($calculator->allocate('0.02', ['1.00', '1.00', '1.00', '1.00']))->toBe(['0.01', '0.01', '0.00', '0.00']);

    $shares = $calculator->allocate('100.00', ['333.33', '333.33', '333.34', '0.00']);
    expect(array_sum(array_map('floatval', $shares)))->toEqual(100.0)
        ->and($shares[3])->toBe('0.00');

    expect($calculator->allocate('10.00', ['10.00']))->toBe(['10.00']);
});

test('money maths has no floating point drift', function () {
    $result = (new SaleCalculator)->calculate([
        saleLine('0.10', 3, '0'),
        saleLine('0.20', 1, '0'),
    ]);

    expect($result['total'])->toBe('0.50');
});

test('tax rounds half up to the cent', function () {
    // 10.05 x 5% = 0.5025 -> 0.50; 10.10 x 5% = 0.505 -> 0.51
    $result = (new SaleCalculator)->calculate([saleLine('10.05', 1, '5'), saleLine('10.10', 1, '5')]);

    expect(array_column($result['lines'], 'tax'))->toBe(['0.50', '0.51']);
});
