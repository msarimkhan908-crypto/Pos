<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => __('Percentage (%)'),
            self::Fixed => __('Fixed amount'),
        };
    }

    /**
     * Price after applying this discount, never below zero.
     */
    public function apply(float $price, float $value): float
    {
        $discounted = match ($this) {
            self::Percent => $price - ($price * $value / 100),
            self::Fixed => $price - $value,
        };

        return round(max($discounted, 0), 2);
    }
}
