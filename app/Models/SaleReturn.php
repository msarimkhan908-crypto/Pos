<?php

namespace App\Models;

use App\Enums\MoneyMethod;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales return (table "returns"; Return is a reserved word in PHP). Created and changed
 * only by App\Services\ReturnService, which calculates every amount on the server.
 */
class SaleReturn extends Model
{
    public const NUMBER_PREFIX = 'RET-';

    public const NUMBER_SEQUENCE = 'return';

    protected $table = 'returns';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'subtotal' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'refund_method' => MoneyMethod::class,
            'reason' => ReturnReason::class,
            'status' => ReturnStatus::class,
        ];
    }

    public static function numberFor(int $sequence): string
    {
        return (settings('general.return_prefix') ?? self::NUMBER_PREFIX).str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return HasMany<ReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id')->orderBy('id');
    }

    public function isPending(): bool
    {
        return $this->status === ReturnStatus::Pending;
    }

    public function customerLabel(): string
    {
        return $this->sale?->customerLabel() ?? ($this->customer?->name ?? __('Walk-in Customer'));
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('return_number', 'like', "%{$term}%")
                ->orWhereHas('sale', fn (Builder $query) => $query
                    ->where('invoice_number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%"))
                ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
        }));
    }
}
