<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A record of goods bought from a supplier. Money columns are always computed by
 * PurchaseCalculator on the server; purchase_number and totals are not fillable.
 */
#[Fillable(['supplier_id', 'purchase_date', 'payment_status', 'status', 'notes'])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    public const NUMBER_PREFIX = 'PUR-';

    protected $attributes = [
        'status' => 'draft',
        'payment_status' => 'unpaid',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => PurchaseStatus::class,
            'payment_status' => PaymentStatus::class,
        ];
    }

    public static function numberFor(int $id): string
    {
        return (settings('general.purchase_prefix') ?? self::NUMBER_PREFIX).str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class)->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseStatus::Draft;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseStatus::Cancelled;
    }

    /**
     * Match the purchase number or the supplier's name / company.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('purchase_number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%"));
        }));
    }
}
