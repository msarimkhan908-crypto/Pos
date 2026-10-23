<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    public const BUSINESS_TYPES = [
        'general' => 'General / Retail',
        'pharmacy' => 'Pharmacy',
        'grocery' => 'Grocery Store',
        'clothing' => 'Clothing Store',
        'electronics' => 'Electronics Shop',
        'restaurant' => 'Restaurant',
        'cafe' => 'Café / Coffee Shop',
        'other' => 'Other',
    ];

    protected $fillable = [
        'name',
        'slug',
        'business_type',
        'license_key',
        'phone',
        'address',
        'plan',
        'plan_price',
        'trial_ends_at',
        'subscribed_until',
        'status',
        'notes',
    ];

    public function isRestaurant(): bool
    {
        return in_array($this->business_type, ['restaurant', 'cafe'], true);
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $org): void {
            if (blank($org->license_key)) {
                $org->license_key = Str::random(40);
            }
        });
    }

    protected $casts = [
        'trial_ends_at' => 'date',
        'subscribed_until' => 'date',
        'plan_price' => 'decimal:2',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owner(): ?User
    {
        return $this->users()->where('is_org_owner', true)->first();
    }

    /** Returns true when this organization's subscription is currently active. */
    public function isActive(): bool
    {
        return match ($this->status) {
            'active' => $this->subscribed_until === null || $this->subscribed_until->isFuture(),
            'trial' => $this->trial_ends_at === null || $this->trial_ends_at->isFuture(),
            default => false,
        };
    }

    /** How many days remain on the current subscription or trial. */
    public function daysRemaining(): int
    {
        $until = $this->status === 'trial' ? $this->trial_ends_at : $this->subscribed_until;

        return $until ? (int) now()->diffInDays($until, false) : 0;
    }

    /** Extend the subscription by the given plan. */
    public function extendSubscription(string $plan): void
    {
        $months = $plan === 'annual' ? 12 : 1;
        $from = ($this->subscribed_until && $this->subscribed_until->isFuture())
            ? $this->subscribed_until
            : now();

        $this->update([
            'plan' => $plan,
            'status' => 'active',
            'subscribed_until' => $from->addMonths($months),
        ]);
    }
}
