<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\TableStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\Money;
use App\Support\NumberSequence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Prices carts, holds and resumes sales, and completes checkouts.
 *
 * Every amount is recalculated here from the database; totals sent by the browser
 * are never used. Checkout and hold each run in one transaction, so a failure part-way
 * saves nothing. Sales do not change stock (there is no inventory module).
 *
 * A cart is ['items' => [...], 'customer_id' => ?int, customer_name / _phone / _address,
 * 'save_customer' => bool, 'delivery_fee' => ?string, 'order_discount_type' => ?string,
 * 'order_discount_value' => ?string], as validated by App\Http\Requests\CartRequest.
 */
class SaleService
{
    /** Largest amount the decimal(14,2) money columns can hold. */
    public const MAX_AMOUNT = '999999999999.99';

    public function __construct(
        private CartResolver $resolver,
        private SaleCalculator $calculator,
        private KitchenService $kitchen,
    ) {}

    /**
     * Server-calculated lines and totals for a cart.
     *
     * @param  array<string, mixed>  $cart
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function quote(array $cart): array
    {
        $lines = $this->resolver->resolve($cart['items'] ?? []);
        $type = DiscountType::tryFrom((string) ($cart['order_discount_type'] ?? ''));
        $value = (string) ($cart['order_discount_value'] ?? '') ?: '0';

        $quote = $this->calculator->calculate($lines, $type, $value);
        $base = bcsub($quote['subtotal'], $quote['item_discount'], 2);

        // The delivery fee is added after tax: it is not taxed and not discounted.
        $quote['delivery_fee'] = bcadd((string) ($cart['delivery_fee'] ?? '') ?: '0', '0', 2);
        $quote['total'] = bcadd($quote['total'], $quote['delivery_fee'], 2);

        if ($type === DiscountType::Percent && bccomp($value, '100', 2) > 0) {
            throw ValidationException::withMessages(['order_discount_value' => __('A percentage discount cannot be more than 100%.')]);
        }

        if ($type === DiscountType::Fixed && bccomp($value, $base, 2) > 0) {
            throw ValidationException::withMessages(['order_discount_value' => __('The order discount cannot be more than the amount after item discounts (:amount).', ['amount' => Money::format($base)])]);
        }

        if (bccomp($quote['subtotal'], self::MAX_AMOUNT, 2) > 0 || bccomp($quote['total'], self::MAX_AMOUNT, 2) > 0) {
            throw ValidationException::withMessages(['items' => __('The sale total is too large.')]);
        }

        return $quote + ['order_discount_type' => $type, 'order_discount_value' => bcadd($value, '0', 2)];
    }

    /**
     * Complete a sale: price the cart, check the payment, save the sale, its lines and payments.
     *
     * @param  array<string, mixed>  $cart
     * @param  array{payment_method: string, cash_received?: ?string, cash_amount?: ?string, card_amount?: ?string}  $payment
     *
     * @throws ValidationException
     */
    public function checkout(array $cart, array $payment, ?User $user): Sale
    {
        return DB::transaction(function () use ($cart, $payment, $user) {
            $quote = $this->quote($cart);
            $settled = $this->settle($quote['total'], $payment);
            $cart = $this->saveNewCustomer($cart);

            $completedAt = now();
            $businessDate = BusinessDay::current($completedAt);
            $deviceId = config('app.device_id') ?: null;

            // Per-day, per-device order number (gap-free, rolled back with the transaction).
            $sequenceKey = 'order:'.($deviceId ?? 'web').':'.$businessDate->toDateString();
            $orderNo = NumberSequence::next($sequenceKey);

            $sale = $this->newSale($cart, $quote, $user, SaleStatus::Completed);
            $sale->forceFill([
                'invoice_number' => Sale::invoiceNumberFor(NumberSequence::next(Sale::INVOICE_SEQUENCE)),
                'device_id' => $deviceId,
                'business_date' => $businessDate->toDateString(),
                'order_no' => $orderNo,
                'payment_method' => $settled['method'],
                'cash_received' => $settled['cash_received'],
                'change_amount' => $settled['change'],
                'completed_at' => $completedAt,
            ])->save();

            $items = $this->saveLines($sale, $quote['lines']);

            foreach ($settled['payments'] as $method => $amount) {
                $sale->payments()->forceCreate(['payment_method' => $method, 'amount' => $amount]);
            }

            // Kitchen items go to the kitchen as one KOT sharing the same order_no; seat the table.
            $this->kitchen->send($sale, $quote['lines'], $items, $user);

            if ($sale->order_type === OrderType::DineIn) {
                $sale->table->update(['status' => TableStatus::Occupied]);
            }

            return $sale;
        });
    }

    /**
     * Park the cart as a held sale. No invoice number, no payments.
     *
     * @param  array<string, mixed>  $cart
     *
     * @throws ValidationException
     */
    public function hold(array $cart, ?User $user): Sale
    {
        return DB::transaction(function () use ($cart, $user) {
            $quote = $this->quote($cart);
            $cart = $this->saveNewCustomer($cart);
            $sale = $this->newSale($cart, $quote, $user, SaleStatus::Held);
            $sale->save();

            $this->saveLines($sale, $quote['lines']);

            return $sale;
        });
    }

    /**
     * Take a held sale back into the cart: returns its cart rows re-priced at today's prices
     * and removes it from the held list. Lines whose product can no longer be sold are
     * dropped and named in 'dropped'.
     *
     * @return array{items: list<array<string, mixed>>, dropped: list<string>, customer_id: ?int, order_discount_type: ?string, order_discount_value: string}
     */
    public function resume(Sale $held): array
    {
        return DB::transaction(function () use ($held) {
            $sale = Sale::query()->held()->whereKey($held->getKey())->lockForUpdate()->first()
                ?? throw new NotFoundHttpException(__('This held sale was already resumed or removed.'));

            $sale->load(['items.modifiers', 'customer', 'table']);

            $rows = $sale->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'quantity' => $item->quantity,
                'modifier_ids' => $item->modifiers->pluck('modifier_id')->filter()->values()->all(),
                'discount_type' => $item->discount_type?->value,
                'discount_value' => (string) $item->discount_value,
                'note' => $item->note,
            ])->all();

            [$lines, $errors] = $this->resolver->resolveEach($rows);

            $dropped = collect($errors)->keys()
                ->map(fn (string $key) => $sale->items[(int) explode('.', $key)[1]]->displayName())
                ->unique()->values()->all();

            $sale->delete();

            return [
                'items' => array_values($lines),
                'dropped' => $dropped,
                'customer_id' => $sale->customer?->isActive() ? $sale->customer_id : null,
                'customer_name' => $sale->customer_name,
                'customer_phone' => $sale->customer_phone,
                'customer_address' => $sale->customer_address,
                'delivery_fee' => (string) $sale->delivery_fee,
                'order_type' => $sale->order_type->value,
                'table_id' => $sale->table?->is_active ? $sale->table_id : null,
                'table_name' => $sale->table?->is_active ? $sale->table->name : null,
                'order_note' => $sale->order_note,
                'order_discount_type' => $sale->order_discount_type?->value,
                'order_discount_value' => (string) $sale->order_discount_value,
            ];
        });
    }

    public function discard(Sale $held): void
    {
        Sale::query()->held()->whereKey($held->getKey())->delete();
    }

    /**
     * Public entry point for SaleEditService (and tests).
     *
     * @param  array<string, mixed>  $payment
     * @return array{method: PaymentMethod, cash_received: ?string, change: string, payments: array<string, string>}
     *
     * @throws ValidationException
     */
    public function settlePublic(string $total, array $payment): array
    {
        return $this->settle($total, $payment);
    }

    /**
     * Check the payment against the server total.
     *
     * @param  array<string, mixed>  $payment
     * @return array{method: PaymentMethod, cash_received: ?string, change: string, payments: array<string, string>}
     *
     * @throws ValidationException
     */
    private function settle(string $total, array $payment): array
    {
        $method = PaymentMethod::from($payment['payment_method']);
        $amount = fn (string $key) => bcadd((string) ($payment[$key] ?? '') ?: '0', '0', 2);
        $fail = fn (string $key, string $message) => throw ValidationException::withMessages([$key => $message]);

        switch ($method) {
            case PaymentMethod::Cash:
                $received = $amount('cash_received');

                if (bccomp($received, $total, 2) < 0) {
                    $fail('cash_received', __('Cash received (:received) is less than the total (:total).', ['received' => Money::format($received), 'total' => Money::format($total)]));
                }

                return ['method' => $method, 'cash_received' => $received, 'change' => bcsub($received, $total, 2), 'payments' => $this->nonZero(['cash' => $total])];

            case PaymentMethod::Card:
                if (bccomp($amount('card_amount'), $total, 2) !== 0) {
                    $fail('card_amount', __('The card amount must equal the total (:total).', ['total' => Money::format($total)]));
                }

                return ['method' => $method, 'cash_received' => null, 'change' => '0.00', 'payments' => $this->nonZero(['card' => $total])];

            default:
                $cash = $amount('cash_amount');
                $card = $amount('card_amount');

                if (bccomp($cash, '0', 2) <= 0 || bccomp($card, '0', 2) <= 0) {
                    $fail('cash_amount', __('A mixed payment needs both a cash and a card amount.'));
                }

                if (bccomp(bcadd($cash, $card, 2), $total, 2) !== 0) {
                    $fail('cash_amount', __('Cash and card must add up to the total (:total). They add up to :paid.', ['total' => Money::format($total), 'paid' => Money::format(bcadd($cash, $card, 2))]));
                }

                return ['method' => $method, 'cash_received' => $cash, 'change' => '0.00', 'payments' => ['cash' => $cash, 'card' => $card]];
        }
    }

    /**
     * "Save as new customer": create the customer from the typed details and link the sale.
     * Ignored when an existing customer is already selected.
     *
     * @param  array<string, mixed>  $cart
     * @return array<string, mixed>
     */
    private function saveNewCustomer(array $cart): array
    {
        if (empty($cart['save_customer']) || filled($cart['customer_id'] ?? null) || blank($cart['customer_name'] ?? null)) {
            return $cart;
        }

        $customer = Customer::create([
            'name' => trim($cart['customer_name']),
            'phone' => filled($cart['customer_phone'] ?? null) ? trim($cart['customer_phone']) : null,
            'address' => filled($cart['customer_address'] ?? null) ? trim($cart['customer_address']) : null,
        ]);

        return ['customer_id' => $customer->id] + $cart;
    }

    /**
     * @param  array<string, string>  $payments
     * @return array<string, string>
     */
    private function nonZero(array $payments): array
    {
        return array_filter($payments, fn (string $amount) => bccomp($amount, '0', 2) > 0);
    }

    /**
     * @param  array<string, mixed>  $cart
     * @param  array<string, mixed>  $quote
     */
    private function newSale(array $cart, array $quote, ?User $user, SaleStatus $status): Sale
    {
        $customer = filled($cart['customer_id'] ?? null) ? Customer::find($cart['customer_id']) : null;
        $typed = fn (string $key) => filled($cart[$key] ?? null) ? trim($cart[$key]) : null;
        $orderType = OrderType::tryFrom((string) ($cart['order_type'] ?? '')) ?? OrderType::TakeAway;

        return (new Sale)->forceFill([
            'customer_id' => $customer?->id,
            // What the cashier typed wins; otherwise copy the linked customer's details.
            'customer_name' => $typed('customer_name') ?? $customer?->name,
            'customer_phone' => $typed('customer_phone') ?? $customer?->phone,
            'customer_address' => $typed('customer_address') ?? $customer?->address,
            'delivery_fee' => $quote['delivery_fee'],
            'order_type' => $orderType,
            'table_id' => $orderType === OrderType::DineIn ? ($cart['table_id'] ?? null) : null,
            'order_note' => $typed('order_note'),
            'user_id' => $user?->getKey(),
            'status' => $status,
            'subtotal' => $quote['subtotal'],
            'item_discount' => $quote['item_discount'],
            'order_discount_type' => $quote['order_discount_type'],
            'order_discount_value' => $quote['order_discount_type'] ? $quote['order_discount_value'] : '0.00',
            'order_discount' => $quote['order_discount'],
            'discount' => $quote['discount'],
            'tax' => $quote['tax'],
            'total' => $quote['total'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<SaleItem> the saved items, in line order
     */
    private function saveLines(Sale $sale, array $lines): array
    {
        $items = [];

        // Track deal parent SaleItems by their original cart item index (_cart_item_index),
        // so that component lines (which reference their parent via deal_line_index) can look
        // up the correct parent even after the lines array has been expanded with components.
        /** @var array<int, SaleItem> $dealParentsByCartIndex */
        $dealParentsByCartIndex = [];

        foreach ($lines as $line) {
            $parentSaleItemId = null;

            if (($line['is_deal_component'] ?? false) && isset($line['deal_line_index'])) {
                $parentSaleItemId = ($dealParentsByCartIndex[$line['deal_line_index']] ?? null)?->id;
            }

            $item = $sale->items()->forceCreate([
                'parent_sale_item_id' => $parentSaleItemId,
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'],
                'product_name' => $line['product_name'],
                'variant_name' => $line['variant_name'],
                'sku' => $line['sku'],
                'note' => $line['note'] ?? null,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'modifiers_total' => $line['modifiers_total'],
                'discount_type' => $line['discount'] !== '0.00' ? $line['discount_type'] : null,
                'discount_value' => $line['discount'] !== '0.00' ? bcadd($line['discount_value'], '0', 2) : '0.00',
                'discount' => $line['discount'],
                'order_discount' => $line['order_discount'],
                'tax_rate' => $line['tax_rate'],
                'tax' => $line['tax'],
                'total' => $line['total'],
            ]);

            foreach ($line['modifiers'] as $modifier) {
                $item->modifiers()->forceCreate($modifier);
            }

            if (($line['is_deal'] ?? false) && isset($line['_cart_item_index'])) {
                $dealParentsByCartIndex[(int) $line['_cart_item_index']] = $item;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Customers the cashier can pick (active ones), lightest shape for the POS.
     *
     * @return Collection<int, array{id: int, name: string, phone: ?string, address: ?string}>
     */
    public function customerOptions(): Collection
    {
        return Customer::query()->active()->orderBy('name')->get(['id', 'name', 'phone', 'address'])
            ->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'address' => $c->address]);
    }
}
