<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A POS sale. Every column is set by App\Services\SaleService from server-side
 * calculations, so nothing is mass assignable.
 *
 * subtotal = sum(quantity x unit_price); discount = item_discount + order_discount;
 * total = subtotal - discount + tax + delivery_fee. Sales do not change stock.
 *
 * customer_name / customer_phone / customer_address are what the cashier typed (or the
 * linked customer's details at the time of sale). All empty means Walk-in Customer.
 */
class Sale extends Model
{
    /** Default prefix; the live one is Settings → General → invoice prefix. */
    public const INVOICE_PREFIX = 'INV-';

    public const INVOICE_SEQUENCE = 'invoice';

    use BelongsToOrganization;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'order_type' => OrderType::class,
            'return_status' => SaleReturnStatus::class,
            'returned_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'order_discount_type' => DiscountType::class,
            'subtotal' => 'decimal:2',
            'item_discount' => 'decimal:2',
            'order_discount_value' => 'decimal:2',
            'order_discount' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'business_date' => 'date:Y-m-d',
        ];
    }

    public static function invoiceNumberFor(int $sequence): string
    {
        $prefix = settings('general.invoice_prefix') ?? self::INVOICE_PREFIX;

        // Desktop: prepend device ID so invoices don't collide with the web server
        if ($deviceId = config('app.device_id')) {
            $prefix = $deviceId.'-'.$prefix;
        }

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->orderBy('id');
    }

    /**
     * Returns made against this sale.
     *
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class)->orderBy('id');
    }

    /**
     * The dining table (Dine In orders only).
     *
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    /**
     * Kitchen order tickets sent for this sale (one per submission; more can follow later).
     *
     * @return HasMany<KitchenOrder, $this>
     */
    public function kitchenOrders(): HasMany
    {
        return $this->hasMany(KitchenOrder::class)->orderBy('id');
    }

    /**
     * Audit records of every edit made to this bill.
     *
     * @return HasMany<SaleEdit, $this>
     */
    public function edits(): HasMany
    {
        return $this->hasMany(SaleEdit::class)->latest('id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The cashier who rang up (or held) the sale.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customerLabel(): string
    {
        return $this->customer_name ?: ($this->customer?->name ?? __('Walk-in Customer'));
    }

    public function isHeld(): bool
    {
        return $this->status === SaleStatus::Held;
    }

    public function isCompleted(): bool
    {
        return $this->status === SaleStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === SaleStatus::Cancelled;
    }

    #[Scope]
    protected function held(Builder $query): void
    {
        $query->where('status', SaleStatus::Held);
    }

    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where('status', SaleStatus::Completed);
    }
}
