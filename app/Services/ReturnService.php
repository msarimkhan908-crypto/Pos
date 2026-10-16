<?php

namespace App\Services;

use App\Enums\MoneyMethod;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\SaleReturnStatus;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\NumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales returns. Everything is calculated here from the sale as it was sold; quantities and
 * amounts sent by the browser are never trusted. Returns never change stock.
 *
 * Refund for returning q units of a line: the line's paid amount (after its discount and its
 * share of the order discount, including tax) spread over its units, to the cent:
 *
 *     refund = round(paid × (already + q) / sold) − round(paid × already / sold)
 *
 * so the refunds for a line always add up to exactly what was paid for it. The delivery fee
 * is not refunded.
 *
 * Pending and completed returns reserve their quantities; cancelling a return releases them.
 */
class ReturnService
{
    private const SCALE = 8;

    /**
     * @param  array{reason: string, refund_method: string, status: string, notes?: ?string, items: list<array{sale_item_id: int, quantity: int, reason?: ?string}>}  $data
     *
     * @throws ValidationException
     */
    public function create(Sale $sale, array $data, User $user): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data, $user) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            if (! $sale->isCompleted()) {
                throw ValidationException::withMessages(['sale_id' => __('Only completed sales can be returned.')]);
            }

            $items = SaleItem::query()->where('sale_id', $sale->id)->lockForUpdate()->get()->keyBy('id');
            $lines = [];

            foreach ($data['items'] as $i => $row) {
                $quantity = (int) $row['quantity'];

                if ($quantity === 0) {
                    continue;
                }

                $item = $items->get((int) $row['sale_item_id'])
                    ?? throw ValidationException::withMessages(["items.{$i}.sale_item_id" => __('This item is not part of the sale.')]);

                if ($quantity > $item->returnableQuantity()) {
                    throw ValidationException::withMessages(["items.{$i}.quantity" => $item->returnableQuantity() === 0
                        ? __(':item has already been fully returned.', ['item' => $item->displayName()])
                        : __('Only :count of :item can be returned.', ['count' => $item->returnableQuantity(), 'item' => $item->displayName()])]);
                }

                $lines[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'refund' => $this->refundFor($item, $quantity),
                    'reason' => $row['reason'] ?? null,
                ];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['items' => __('Enter a quantity for at least one item.')]);
            }

            $status = ReturnStatus::from($data['status']);
            $return = (new SaleReturn)->forceFill([
                'return_number' => SaleReturn::numberFor(NumberSequence::next(SaleReturn::NUMBER_SEQUENCE)),
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'return_date' => today(),
                'subtotal' => array_reduce($lines, fn (string $sum, array $l) => bcadd($sum, bcmul((string) $l['item']->unit_price, (string) $l['quantity'], 2), 2), '0.00'),
                'refund_amount' => array_reduce($lines, fn (string $sum, array $l) => bcadd($sum, $l['refund'], 2), '0.00'),
                'refund_method' => MoneyMethod::from($data['refund_method']),
                'reason' => ReturnReason::from($data['reason']),
                'status' => $status,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'processed_by' => $user->getKey(),
            ]);
            $return->save();

            foreach ($lines as $line) {
                $return->items()->forceCreate([
                    'sale_item_id' => $line['item']->id,
                    'product_id' => $line['item']->product_id,
                    'variant_id' => $line['item']->variant_id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['item']->unit_price,
                    'refund_amount' => $line['refund'],
                    'reason' => $line['reason'],
                ]);

                $line['item']->forceFill(['returned_quantity' => $line['item']->returned_quantity + $line['quantity']])->save();
            }

            $this->syncSale($sale);

            return $return;
        });
    }

    /**
     * Pending -> Completed (refund paid out).
     *
     * @throws ValidationException
     */
    public function complete(SaleReturn $return, MoneyMethod $method): SaleReturn
    {
        return DB::transaction(function () use ($return, $method) {
            $return = SaleReturn::query()->lockForUpdate()->findOrFail($return->getKey());
            $this->mustBePending($return);

            $return->forceFill(['status' => ReturnStatus::Completed, 'refund_method' => $method])->save();
            $this->syncSale($return->sale);

            return $return;
        });
    }

    /**
     * Pending -> Cancelled: the items become returnable again.
     *
     * @throws ValidationException
     */
    public function cancel(SaleReturn $return): SaleReturn
    {
        return DB::transaction(function () use ($return) {
            $return = SaleReturn::query()->lockForUpdate()->findOrFail($return->getKey());
            $this->mustBePending($return);

            $return->forceFill(['status' => ReturnStatus::Cancelled])->save();
            $this->releaseItems($return);
            $this->syncSale($return->sale);

            return $return;
        });
    }

    /**
     * Delete a pending or cancelled return. Completed returns are financial records and stay.
     *
     * @throws ValidationException
     */
    public function delete(SaleReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $return = SaleReturn::query()->lockForUpdate()->findOrFail($return->getKey());

            if ($return->status === ReturnStatus::Completed) {
                throw ValidationException::withMessages(['return' => __('Completed returns cannot be deleted.')]);
            }

            if ($return->isPending()) {
                $this->releaseItems($return);
            }

            $sale = $return->sale;
            $return->delete();
            $this->syncSale($sale);
        });
    }

    /**
     * Refund for returning $quantity more units of $item, to the cent.
     */
    public function refundFor(SaleItem $item, int $quantity): string
    {
        if ($item->quantity <= 0) {
            return '0.00';
        }

        $paid = $item->paidAmount();
        $share = fn (int $units) => $this->round(bcdiv(bcmul($paid, (string) $units, self::SCALE), (string) $item->quantity, self::SCALE));

        return bcsub($share($item->returned_quantity + $quantity), $share($item->returned_quantity), 2);
    }

    /**
     * @throws ValidationException
     */
    private function mustBePending(SaleReturn $return): void
    {
        if (! $return->isPending()) {
            throw ValidationException::withMessages(['status' => __('Only pending returns can be changed. This return is :status.', ['status' => $return->status->label()])]);
        }
    }

    private function releaseItems(SaleReturn $return): void
    {
        foreach ($return->items()->with('saleItem')->get() as $line) {
            $line->saleItem->forceFill([
                'returned_quantity' => max(0, $line->saleItem->returned_quantity - $line->quantity),
            ])->save();
        }
    }

    /**
     * Recalculate the sale's return status (from reserved quantities) and refunded amount
     * (completed returns only).
     */
    private function syncSale(Sale $sale): void
    {
        $items = SaleItem::query()->where('sale_id', $sale->id)->get(['quantity', 'returned_quantity']);
        $returned = $items->sum('returned_quantity');

        $status = match (true) {
            $returned === 0 => SaleReturnStatus::None,
            $items->every(fn ($i) => $i->returned_quantity >= $i->quantity) => SaleReturnStatus::Full,
            default => SaleReturnStatus::Partial,
        };

        $refunded = (string) SaleReturn::query()->where('sale_id', $sale->id)->where('status', ReturnStatus::Completed)->sum('refund_amount');

        $sale->forceFill(['return_status' => $status, 'returned_amount' => bcadd($refunded ?: '0', '0', 2)])->save();
    }

    /**
     * Round a non-negative decimal string to 2 places, half up.
     */
    private function round(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }
}
