<?php

use App\Services\PurchaseCalculator;

test('a line total is quantity times price, minus discount, plus tax', function () {
    $line = (new PurchaseCalculator)->line(['quantity' => '3', 'purchase_price' => '250.00', 'discount' => '50', 'tax' => '17.50']);

    expect($line['gross'])->toBe('750.00')
        ->and($line['line_total'])->toBe('717.50');
});

test('totals are summed across lines', function () {
    $totals = (new PurchaseCalculator)->calculate([
        ['quantity' => '2', 'purchase_price' => '100', 'discount' => '10', 'tax' => '5'],
        ['quantity' => '1.5', 'purchase_price' => '40', 'discount' => '0', 'tax' => '0'],
        ['quantity' => '10', 'purchase_price' => '0.99', 'discount' => '0.90', 'tax' => '1.20'],
    ]);

    expect($totals['subtotal'])->toBe('269.90')   // 200 + 60 + 9.90
        ->and($totals['discount'])->toBe('10.90')
        ->and($totals['tax'])->toBe('6.20')
        ->and($totals['total'])->toBe('265.20')
        ->and(array_column($totals['items'], 'line_total'))->toBe(['195.00', '60.00', '10.20']);
});

test('money is exact where floats would drift', function () {
    // 0.1 + 0.2 != 0.3 in floating point; 3 x 0.1 = 0.30000000000000004.
    $totals = (new PurchaseCalculator)->calculate([
        ['quantity' => '3', 'purchase_price' => '0.10'],
        ['quantity' => '1', 'purchase_price' => '0.20'],
    ]);

    expect($totals['total'])->toBe('0.50');
});

test('fractional quantities round half up to the cent', function () {
    $calculator = new PurchaseCalculator;

    expect($calculator->line(['quantity' => '0.333', 'purchase_price' => '10.00'])['gross'])->toBe('3.33')  // 3.330
        ->and($calculator->line(['quantity' => '0.125', 'purchase_price' => '0.10'])['gross'])->toBe('0.01') // 0.0125
        ->and($calculator->line(['quantity' => '2.5', 'purchase_price' => '1.99'])['gross'])->toBe('4.98');  // 4.975
});

test('blank discount and tax count as zero', function () {
    $line = (new PurchaseCalculator)->line(['quantity' => '1', 'purchase_price' => '12.34', 'discount' => null, 'tax' => '']);

    expect($line['discount'])->toBe('0.00')
        ->and($line['tax'])->toBe('0.00')
        ->and($line['line_total'])->toBe('12.34');
});

test('large amounts stay exact', function () {
    $line = (new PurchaseCalculator)->line(['quantity' => '999999.999', 'purchase_price' => '9999999.99']);

    expect($line['gross'])->toBe('9999999980000.00');
});
