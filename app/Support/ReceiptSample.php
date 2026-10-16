<?php

namespace App\Support;

use App\Enums\DiscountType;
use App\Enums\KitchenStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\KitchenStation;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemModifier;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Sample sale and KOT for the receipt / KOT live previews in Settings.
 * Built in memory only: nothing here is ever saved to the database.
 */
class ReceiptSample
{
    public static function sale(): Sale
    {
        $cashier = (new User)->forceFill(['name' => __('Sample Cashier')]);
        $table = (new RestaurantTable)->forceFill(['name' => __('Table 5')]);

        $burger = self::item(__('Chicken Burger'), __('Large'), 2, '700.00', '0.00', 'No onions', [[__('Extra cheese'), '50.00']]);
        $fries = self::item(__('French Fries'), null, 1, '300.00', '30.00');
        $drink = self::item(__('Soft Drink'), null, 2, '200.00', '0.00');

        // A deal: "Zinger Combo" parent with two components below it.
        $zingerCombo = self::item(__('Zinger Combo'), null, 1, '999.00', '0.00');
        $zingerCombo->forceFill(['id' => 99]);

        $zingerBurgerComp = self::item(__('Zinger Burger'), null, 1, '0.00', '0.00');
        $zingerBurgerComp->forceFill(['parent_sale_item_id' => 99])->setRelation('modifiers', new Collection);

        $cokeComp = self::item(__('Coke'), null, 1, '0.00', '0.00');
        $cokeComp->forceFill(['parent_sale_item_id' => 99])->setRelation('modifiers', new Collection);

        $sale = (new Sale)->forceFill([
            'invoice_number' => Sale::invoiceNumberFor(123),
            'order_no' => 28,
            'business_date' => now()->toDateString(),
            'status' => SaleStatus::Completed,
            'order_type' => OrderType::DineIn,
            'customer_name' => __('Sample Customer'),
            'customer_phone' => '0300 0000000',
            'subtotal' => '3099.00',
            'item_discount' => '30.00',
            'order_discount' => '0.00',
            'discount' => '30.00',
            'tax' => '152.10',
            'delivery_fee' => '0.00',
            'total' => '3221.10',
            'payment_method' => PaymentMethod::Cash,
            'cash_received' => '3500.00',
            'change_amount' => '278.90',
            'completed_at' => now(),
        ]);

        return $sale
            ->setRelation('items', new Collection([$burger, $fries, $drink, $zingerCombo, $zingerBurgerComp, $cokeComp]))
            ->setRelation('payments', new Collection([(new SalePayment)->forceFill(['payment_method' => PaymentMethod::Cash, 'amount' => '3221.10'])]))
            ->setRelation('user', $cashier)
            ->setRelation('table', $table)
            ->setRelation('customer', null);
    }

    public static function kitchenOrder(): KitchenOrder
    {
        $sale = self::sale();
        $stations = [
            __('Grill') => (new KitchenStation)->forceFill(['name' => __('Grill')]),
            __('Main Kitchen') => (new KitchenStation)->forceFill(['name' => __('Main Kitchen')]),
            __('Drinks') => (new KitchenStation)->forceFill(['name' => __('Drinks')]),
        ];

        // KOT shows only items that go to a kitchen station (not deal parent lines, not components).
        // For the sample, use the first three items (burger, fries, drink) — they each map to a station.
        $kotItems = collect($sale->items)
            ->filter(fn (SaleItem $item) => $item->parent_sale_item_id === null && (string) $item->unit_price !== '0.00')
            ->values();

        $items = $kotItems->map(function (SaleItem $item, int $i) use ($stations) {
            $station = array_values($stations)[$i % count($stations)];

            return (new KitchenOrderItem)->forceFill([
                'id' => $i + 1,
                'quantity' => $item->quantity,
                'notes' => $item->note,
                'status' => KitchenStatus::Pending,
            ])->setRelation('saleItem', $item)->setRelation('station', $station);
        });

        return (new KitchenOrder)->forceFill([
            'kot_number' => KitchenOrder::numberFor(28),
            'order_no' => 28,
            'order_type' => OrderType::DineIn,
            'status' => KitchenStatus::Pending,
            'notes' => __('Customer is waiting outside.'),
            'created_at' => now(),
        ])
            ->setRelation('items', new Collection($items->all()))
            ->setRelation('sale', $sale)
            ->setRelation('table', $sale->table)
            ->setRelation('creator', $sale->user);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $modifiers
     */
    private static function item(string $name, ?string $variant, int $quantity, string $unitPrice, string $discount, ?string $note = null, array $modifiers = []): SaleItem
    {
        $gross = bcmul($unitPrice, (string) $quantity, 2);

        return (new SaleItem)->forceFill([
            'product_name' => $name,
            'variant_name' => $variant,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_type' => (float) $discount > 0 ? DiscountType::Fixed : null,
            'discount' => $discount,
            'total' => bcsub($gross, $discount, 2),
            'note' => $note,
        ])->setRelation('modifiers', new Collection(array_map(
            fn (array $m) => (new SaleItemModifier)->forceFill(['name' => $m[0], 'price' => $m[1]]),
            $modifiers,
        )));
    }
}
