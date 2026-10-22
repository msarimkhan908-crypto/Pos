<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of business expense (Rent, Electricity, Salaries…). Categories in use cannot be
 * deleted; deactivate them instead.
 */
#[Fillable(['name', 'description', 'status'])]
class ExpenseCategory extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', RecordStatus::Active);
    }
}
