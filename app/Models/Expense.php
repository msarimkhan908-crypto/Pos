<?php

namespace App\Models;

use App\Enums\MoneyMethod;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business expense. expense_number (EXP-000001…) and created_by are set by
 * ExpenseController, not mass assignable.
 */
#[Fillable(['category_id', 'amount', 'expense_date', 'payment_method', 'description', 'reference'])]
class Expense extends Model
{
    use BelongsToOrganization, HasFactory;

    public const NUMBER_PREFIX = 'EXP-';

    public const NUMBER_SEQUENCE = 'expense';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'payment_method' => MoneyMethod::class,
        ];
    }

    public static function numberFor(int $sequence): string
    {
        return (settings('general.expense_prefix') ?? self::NUMBER_PREFIX).str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('expense_number', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%");
        }));
    }
}
