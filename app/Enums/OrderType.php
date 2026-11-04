<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case TakeAway = 'take_away';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => __('Dine In'),
            self::TakeAway => __('Take Away'),
            self::Delivery => __('Delivery'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }

    /**
     * Order types available for a given business type.
     * Non-restaurant businesses only get Take Away (a simple "sale" mode).
     *
     * @return array<string, string>
     */
    public static function optionsForBusiness(string $businessType): array
    {
        $isRestaurant = in_array($businessType, ['restaurant', 'cafe'], true);

        if (! $isRestaurant) {
            return [self::TakeAway->value => self::TakeAway->label()];
        }

        return self::options();
    }
}
