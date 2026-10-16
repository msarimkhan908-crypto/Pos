<?php

namespace App\Enums;

/**
 * How money left the business: refunds (returns) and expenses.
 */
enum MoneyMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Bank = 'bank';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
            self::Bank => __('Bank transfer'),
            self::Other => __('Other'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
